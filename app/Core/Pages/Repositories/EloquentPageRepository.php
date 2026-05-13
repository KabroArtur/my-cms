<?php

namespace App\Core\Pages\Repositories;

use App\Core\Languages\Services\LanguageManager;
use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Support\Services\CmsCacheService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Eloquent-репозиторий работает с таблицей страниц через модель Laravel.
 * Он держит все базовые запросы домена в одном месте.
 */
class EloquentPageRepository implements PageRepository
{
    protected bool $scheduledPublicationsRefreshed = false;

    public function __construct(
        protected LanguageManager $languages,
        protected SettingsManager $settings,
        protected CmsCacheService $cache,
    )
    {
    }

    public function all(array $filters = []): Collection
    {
        $this->refreshScheduledPublications();

        return $this->applyAdminFilters(
            Page::query()->with(['parent', 'featuredMedia', 'creator', 'language']),
            $filters,
        )
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $this->refreshScheduledPublications();

        return $this->applyAdminFilters(
            Page::query()->with(['parent', 'featuredMedia', 'creator', 'language']),
            $filters,
        )
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function paginateTrashed(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->applyAdminFilters(Page::query(), $filters)
            ->onlyTrashed()
            ->with(['parent', 'featuredMedia', 'creator', 'language'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Page
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia', 'creator', 'language'])
            ->find($id);
    }

    public function findTrashedById(int $id): ?Page
    {
        return Page::query()
            ->onlyTrashed()
            ->with(['parent', 'featuredMedia', 'creator', 'language'])
            ->find($id);
    }

    public function findBySlug(string $slug, ?int $languageId = null): ?Page
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia', 'creator', 'language'])
            ->when($languageId !== null, fn (Builder $query) => $query->where('language_id', $languageId))
            ->where('slug', $slug)
            ->first();
    }

    protected static bool $homePageAutoSynced = false;

    public function findHomePage(?int $languageId = null): ?Page
    {
        $this->refreshScheduledPublications();

        $query = $this->publicQuery($languageId);

        $homePage = (clone $query)
            ->where('is_home', true)
            ->orderBy('sort_order')
            ->first();

        if ($homePage !== null) {
            return $homePage;
        }

        $fallback = (clone $query)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();

        if ($fallback !== null && ! static::$homePageAutoSynced) {
            static::$homePageAutoSynced = true;
            $fallback->forceFill(['is_home' => true])->saveQuietly();

            if ($this->isDefaultLanguagePage($fallback)) {
                $this->settings->rememberHomePage($fallback->id);
            }
        }

        return $fallback;
    }

    public function findPublicBySlug(string $slug, ?int $languageId = null): ?Page
    {
        $this->refreshScheduledPublications();

        $path = trim($slug, '/');
        $lastSegment = Str::of($path)->afterLast('/')->value();

        $legacyPage = $this->publicQuery($languageId)
            ->where('slug', $path)
            ->whereNull('parent_id')
            ->first();

        if ($legacyPage !== null) {
            return $legacyPage;
        }

        return $this->publicQuery($languageId)
            ->where('slug', $lastSegment)
            ->get()
            ->first(fn (Page $page): bool => $page->path === $path);
    }

    public function publicNavigation(?int $languageId = null): Collection
    {
        $this->refreshScheduledPublications();

        return $this->publicQuery($languageId)->get();
    }

    /**
     * Репозиторий переводит запланированные страницы в опубликованные.
     */
    public function publishScheduledPages(): int
    {
        $updated = Page::query()
            ->where('status', PageStatus::Scheduled->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update([
                'status' => PageStatus::Published->value,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->cache->invalidateAll(reason: 'scheduled-pages-published');
        }

        return $updated;
    }

    /**
     * Репозиторий обновляет overdue scheduled-страницы до чтения данных.
     */
    protected function refreshScheduledPublications(): void
    {
        if ($this->scheduledPublicationsRefreshed) {
            return;
        }

        $this->publishScheduledPages();
        $this->scheduledPublicationsRefreshed = true;
    }

    public function create(PageData $data): Page
    {
        $languageId = $data->languageId ?? $this->languages->defaultId();
        $parent = $this->resolveParentPage(parentId: $data->parentId, expectedLanguageId: $languageId);
        $attributes = $data->toArray();
        $attributes['language_id'] = $languageId;
        $attributes['translation_group_id'] = $this->languages->translationGroupId($data->translationGroupId);
        $attributes['parent_id'] = $parent?->id;
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug, languageId: $languageId);

        $page = Page::query()->create($attributes);

        $this->syncHomePage($page, (bool) $attributes['is_home']);
        $this->cache->invalidateAll(reason: 'page-created');

        return $page->fresh(['parent', 'featuredMedia', 'creator', 'language']);
    }

    public function update(Page $page, PageData $data): Page
    {
        $languageId = $data->languageId ?? $page->language_id ?? $this->languages->defaultId();
        $parent = $this->resolveParentPage(page: $page, parentId: $data->parentId, expectedLanguageId: $languageId);
        $attributes = $data->toArray();
        $attributes['language_id'] = $languageId;
        $attributes['translation_group_id'] = $this->languages->translationGroupId($data->translationGroupId ?? $page->translation_group_id);
        $attributes['parent_id'] = $parent?->id;
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug, $page->id, $languageId);

        $page->fill($attributes);
        $page->save();

        $this->syncHomePage($page, (bool) $attributes['is_home']);
        $this->cache->invalidateAll(reason: 'page-updated');

        return $page->fresh(['parent', 'featuredMedia', 'creator', 'language']);
    }

    public function delete(Page $page): void
    {
        $wasHomePage = (bool) $page->is_home;
        $languageId = $page->language_id === null ? null : (int) $page->language_id;

        $page->delete();

        if ($wasHomePage) {
            $this->assignFallbackHomePageForLanguage($languageId);
        }

        $this->cache->invalidateAll(reason: 'page-deleted');
    }

    public function restore(Page $page): Page
    {
        $page->restore();

        $restoredPage = $page->fresh(['parent', 'featuredMedia', 'creator', 'language']);
        $this->syncHomePage($restoredPage, (bool) $restoredPage->is_home);
        $this->cache->invalidateAll(reason: 'page-restored');

        return $restoredPage->fresh(['parent', 'featuredMedia', 'creator', 'language']);
    }

    public function forceDelete(Page $page): void
    {
        $wasHomePage = (bool) $page->is_home;
        $languageId = $page->language_id === null ? null : (int) $page->language_id;

        $page->forceDelete();

        if ($wasHomePage) {
            $this->assignFallbackHomePageForLanguage($languageId);
        }

        $this->cache->invalidateAll(reason: 'page-force-deleted');
    }

    public function syncTree(array $nodes): void
    {
        $pages = Page::query()->get(['id', 'parent_id']);
        $pageIds = $pages->pluck('id')->sort()->values();
        $nodeCollection = collect($nodes)
            ->map(fn (array $node): array => [
                'id' => (int) $node['id'],
                'parent_id' => isset($node['parent_id']) ? (int) $node['parent_id'] : null,
                'sort_order' => (int) $node['sort_order'],
            ]);

        if ($nodeCollection->pluck('id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tree' => 'В дереве страниц обнаружены повторяющиеся записи.',
            ]);
        }

        if ($nodeCollection->pluck('id')->sort()->values()->all() !== $pageIds->all()) {
            throw ValidationException::withMessages([
                'tree' => 'Передан неполный набор страниц для сохранения дерева.',
            ]);
        }

        $parentMap = $pages
            ->mapWithKeys(fn (Page $page): array => [$page->id => $page->parent_id === null ? null : (int) $page->parent_id])
            ->all();

        foreach ($nodeCollection as $node) {
            if ($node['parent_id'] !== null && ! $pageIds->contains($node['parent_id'])) {
                throw ValidationException::withMessages([
                    'tree' => 'Для дерева указан несуществующий родитель.',
                ]);
            }

            if ($node['parent_id'] === $node['id']) {
                throw ValidationException::withMessages([
                    'tree' => 'Страница не может быть родителем самой себе.',
                ]);
            }

            $parentMap[$node['id']] = $node['parent_id'];
        }

        $visited = [];
        $stack = [];

        foreach ($parentMap as $pageId => $parentId) {
            if ($this->containsCycle($pageId, $parentMap, $visited, $stack)) {
                throw ValidationException::withMessages([
                    'tree' => 'Дерево страниц содержит циклическую вложенность.',
                ]);
            }
        }

        DB::transaction(function () use ($nodeCollection): void {
            foreach ($nodeCollection as $node) {
                Page::query()
                    ->whereKey($node['id'])
                    ->update([
                        'parent_id' => $node['parent_id'],
                        'sort_order' => $node['sort_order'],
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->cache->invalidateAll(reason: 'page-tree-updated');
    }

    /**
     * Репозиторий строит уникальный slug для новой или существующей страницы.
     */
    protected function resolveUniqueSlug(string $value, ?int $ignoreId = null, ?int $languageId = null): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'page';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId, $languageId)) {
            $slug = $this->appendSlugSuffix($baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }

    /**
     * Репозиторий проверяет валидность родителя и защищает от циклов.
     */
    protected function resolveParentPage(?Page $page = null, ?int $parentId = null, ?int $expectedLanguageId = null): ?Page
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Page::query()->find($parentId);

        if ($parent === null) {
            return null;
        }

        if ($page !== null && $parent->id === $page->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Страница не может быть родителем самой себе.',
            ]);
        }

        if ($page !== null && $this->isDescendant($parent, $page)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Нельзя вложить страницу в ее собственную дочернюю ветку.',
            ]);
        }

        $expectedLanguageId ??= $page?->language_id;

        if ($expectedLanguageId !== null && (int) $parent->language_id !== (int) $expectedLanguageId) {
            throw ValidationException::withMessages([
                'parent_id' => __('languages.messages.parent_language_mismatch'),
            ]);
        }

        return $parent;
    }

    /**
     * Репозиторий проверяет, не выбран ли потомок как новый родитель.
     */
    protected function isDescendant(Page $candidateParent, Page $page): bool
    {
        $currentParent = $candidateParent;

        while ($currentParent !== null) {
            if ($currentParent->id === $page->id) {
                return true;
            }

            $currentParent = $currentParent->parent;
        }

        return false;
    }

    /**
     * Репозиторий добавляет суффикс к последнему сегменту пути.
     */
    protected function appendSlugSuffix(string $slug, int $suffix): string
    {
        $segments = explode('/', $slug);
        $lastIndex = array_key_last($segments);

        if ($lastIndex === null) {
            return 'page-'.$suffix;
        }

        $segments[$lastIndex] = $segments[$lastIndex].'-'.$suffix;

        return implode('/', $segments);
    }

    /**
     * Репозиторий проверяет наличие циклов в карте родительских связей.
     *
     * @param array<int, int|null> $parentMap
     * @param array<int, bool> $visited
     * @param array<int, bool> $stack
     */
    protected function containsCycle(int $pageId, array $parentMap, array &$visited, array &$stack): bool
    {
        if (($stack[$pageId] ?? false) === true) {
            return true;
        }

        if (($visited[$pageId] ?? false) === true) {
            return false;
        }

        $visited[$pageId] = true;
        $stack[$pageId] = true;

        $parentId = $parentMap[$pageId] ?? null;

        if ($parentId !== null && $this->containsCycle($parentId, $parentMap, $visited, $stack)) {
            return true;
        }

        $stack[$pageId] = false;

        return false;
    }

    /**
     * Репозиторий проверяет, занят ли slug другой записью.
     */
    protected function slugExists(string $slug, ?int $ignoreId = null, ?int $languageId = null): bool
    {
        $query = Page::query()
            ->withTrashed()
            ->where('slug', $slug);

        if ($languageId !== null) {
            $query->where('language_id', $languageId);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    /**
     * Репозиторий строит публичный запрос для сайта.
     */
    protected function publicQuery(?int $languageId = null): Builder
    {
        $languageId ??= $this->languages->defaultId();

        return Page::query()
            ->with(['featuredMedia', 'language'])
            ->whereIn('status', [
                PageStatus::Published->value,
                PageStatus::Scheduled->value,
            ])
            ->where('visibility', PageVisibility::Public->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('language_id', $languageId)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Репозиторий удерживает единственную главную страницу сайта.
     */
    protected function syncHomePage(Page $page, bool $requestedHome): void
    {
        if ($requestedHome) {
            Page::query()
                ->whereKeyNot($page->id)
                ->where('is_home', true)
                ->where('language_id', $page->language_id)
                ->update(['is_home' => false]);

            if (! $page->is_home) {
                $page->forceFill(['is_home' => true])->saveQuietly();
            }

            $this->settings->rememberHomePage($page->id, (int) $page->language_id);

            return;
        }

        $anotherHomeExists = Page::query()
            ->whereKeyNot($page->id)
            ->where('is_home', true)
            ->where('language_id', $page->language_id)
            ->exists();

        if ($anotherHomeExists) {
            return;
        }

        $page->forceFill(['is_home' => true])->saveQuietly();
        $this->settings->rememberHomePage($page->id, (int) $page->language_id);
    }

    /**
     * Репозиторий назначает запасную главную страницу после удаления текущей.
     */
    protected function assignFallbackHomePage(): void
    {
        $this->assignFallbackHomePageForLanguage($this->languages->defaultId());
    }

    protected function assignFallbackHomePageForLanguage(?int $languageId): void
    {
        if ($languageId === null) {
            return;
        }

        $fallbackPage = Page::query()
            ->where('language_id', $languageId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();

        if ($fallbackPage === null) {
            $this->settings->rememberHomePage(null, $languageId);

            return;
        }

        Page::query()
            ->where('is_home', true)
            ->where('language_id', $fallbackPage->language_id)
            ->update(['is_home' => false]);
        $fallbackPage->forceFill(['is_home' => true])->saveQuietly();
        $this->settings->rememberHomePage($fallbackPage->id, (int) $fallbackPage->language_id);
    }

    protected function applyAdminFilters(Builder $query, array $filters): Builder
    {
        $languageId = $filters['language_id'] ?? null;

        if ($languageId !== null && $languageId !== '' && $languageId !== 'all') {
            $query->where('language_id', (int) $languageId);
        }

        return $query;
    }

    protected function isDefaultLanguagePage(Page $page): bool
    {
        return (int) $page->language_id === (int) $this->languages->defaultId();
    }
}