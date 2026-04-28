<?php

namespace App\Core\Pages\Repositories;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
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
    public function __construct(protected SettingsManager $settings)
    {
    }

    public function all(): Collection
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia'])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia'])
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function paginateTrashed(int $perPage = 15): LengthAwarePaginator
    {
        return Page::query()
            ->onlyTrashed()
            ->with(['parent', 'featuredMedia'])
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Page
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia'])
            ->find($id);
    }

    public function findTrashedById(int $id): ?Page
    {
        return Page::query()
            ->onlyTrashed()
            ->with(['parent', 'featuredMedia'])
            ->find($id);
    }

    public function findBySlug(string $slug): ?Page
    {
        $this->refreshScheduledPublications();

        return Page::query()
            ->with(['parent', 'featuredMedia'])
            ->where('slug', $slug)
            ->first();
    }

    public function findHomePage(): ?Page
    {
        $this->refreshScheduledPublications();

        $query = $this->publicQuery();

        $homePage = (clone $query)
            ->where('is_home', true)
            ->orderBy('sort_order')
            ->first();

        if ($homePage !== null) {
            return $homePage;
        }

        return (clone $query)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();
    }

    public function findPublicBySlug(string $slug): ?Page
    {
        $this->refreshScheduledPublications();

        $path = trim($slug, '/');
        $lastSegment = Str::of($path)->afterLast('/')->value();

        $legacyPage = $this->publicQuery()
            ->where('slug', $path)
            ->whereNull('parent_id')
            ->first();

        if ($legacyPage !== null) {
            return $legacyPage;
        }

        return $this->publicQuery()
            ->where('slug', $lastSegment)
            ->get()
            ->first(fn (Page $page): bool => $page->path === $path);
    }

    public function publicNavigation(): Collection
    {
        $this->refreshScheduledPublications();

        return $this->publicQuery()->get();
    }

    /**
     * Репозиторий переводит запланированные страницы в опубликованные.
     */
    public function publishScheduledPages(): int
    {
        return Page::query()
            ->where('status', PageStatus::Scheduled->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update([
                'status' => PageStatus::Published->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Репозиторий обновляет overdue scheduled-страницы до чтения данных.
     */
    protected function refreshScheduledPublications(): void
    {
        $this->publishScheduledPages();
    }

    public function create(PageData $data): Page
    {
        $parent = $this->resolveParentPage(parentId: $data->parentId);
        $attributes = $data->toArray();
        $attributes['parent_id'] = $parent?->id;
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug);

        $page = Page::query()->create($attributes);

        $this->syncHomePage($page, (bool) $attributes['is_home']);

        return $page->fresh(['parent', 'featuredMedia']);
    }

    public function update(Page $page, PageData $data): Page
    {
        $parent = $this->resolveParentPage(page: $page, parentId: $data->parentId);
        $attributes = $data->toArray();
        $attributes['parent_id'] = $parent?->id;
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug, $page->id);

        $page->fill($attributes);
        $page->save();

        $this->syncHomePage($page, (bool) $attributes['is_home']);

        return $page->fresh(['parent', 'featuredMedia']);
    }

    public function delete(Page $page): void
    {
        $wasHomePage = (bool) $page->is_home;

        $page->delete();

        if ($wasHomePage) {
            $this->assignFallbackHomePage();
        }
    }

    public function restore(Page $page): Page
    {
        $page->restore();

        $restoredPage = $page->fresh(['parent', 'featuredMedia']);
        $this->syncHomePage($restoredPage, (bool) $restoredPage->is_home);

        return $restoredPage->fresh(['parent', 'featuredMedia']);
    }

    public function forceDelete(Page $page): void
    {
        $wasHomePage = (bool) $page->is_home;

        $page->forceDelete();

        if ($wasHomePage) {
            $this->assignFallbackHomePage();
        }
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
    }

    /**
     * Репозиторий строит уникальный slug для новой или существующей страницы.
     */
    protected function resolveUniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'page';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $this->appendSlugSuffix($baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }

    /**
     * Репозиторий проверяет валидность родителя и защищает от циклов.
     */
    protected function resolveParentPage(?Page $page = null, ?int $parentId = null): ?Page
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
    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Page::query()
            ->withTrashed()
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    /**
     * Репозиторий строит публичный запрос для сайта.
     */
    protected function publicQuery(): Builder
    {
        return Page::query()
            ->with('featuredMedia')
            ->whereIn('status', [
                PageStatus::Published->value,
                PageStatus::Scheduled->value,
            ])
            ->where('visibility', PageVisibility::Public->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
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
                ->update(['is_home' => false]);

            if (! $page->is_home) {
                $page->forceFill(['is_home' => true])->saveQuietly();
            }

            $this->settings->rememberHomePage($page->id);

            return;
        }

        $anotherHomeExists = Page::query()
            ->whereKeyNot($page->id)
            ->where('is_home', true)
            ->exists();

        if ($anotherHomeExists) {
            return;
        }

        $page->forceFill(['is_home' => true])->saveQuietly();
        $this->settings->rememberHomePage($page->id);
    }

    /**
     * Репозиторий назначает запасную главную страницу после удаления текущей.
     */
    protected function assignFallbackHomePage(): void
    {
        $fallbackPage = Page::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->first();

        if ($fallbackPage === null) {
            $this->settings->rememberHomePage(null);

            return;
        }

        Page::query()->where('is_home', true)->update(['is_home' => false]);
        $fallbackPage->forceFill(['is_home' => true])->saveQuietly();
        $this->settings->rememberHomePage($fallbackPage->id);
    }
}