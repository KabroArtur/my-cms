<?php

namespace App\Core\Pages\Repositories;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Eloquent-репозиторий работает с таблицей страниц через модель Laravel.
 * Он держит все базовые запросы домена в одном месте.
 */
class EloquentPageRepository implements PageRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Page::query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    public function paginateTrashed(int $perPage = 15): LengthAwarePaginator
    {
        return Page::query()
            ->onlyTrashed()
            ->with('parent')
            ->orderByDesc('deleted_at')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Page
    {
        return Page::query()->find($id);
    }

    public function findTrashedById(int $id): ?Page
    {
        return Page::query()
            ->onlyTrashed()
            ->find($id);
    }

    public function findBySlug(string $slug): ?Page
    {
        return Page::query()
            ->where('slug', $slug)
            ->first();
    }

    public function findHomePage(): ?Page
    {
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
        return $this->publicQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function create(PageData $data): Page
    {
        $attributes = $data->toArray();
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug);

        $page = Page::query()->create($attributes);

        $this->syncHomePage($page, (bool) $attributes['is_home']);

        return $page->fresh();
    }

    public function update(Page $page, PageData $data): Page
    {
        $attributes = $data->toArray();
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug, $page->id);

        $page->fill($attributes);
        $page->save();

        $this->syncHomePage($page, (bool) $attributes['is_home']);

        return $page->fresh();
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

        $restoredPage = $page->fresh();
        $this->syncHomePage($restoredPage, (bool) $restoredPage->is_home);

        return $restoredPage->fresh();
    }

    public function forceDelete(Page $page): void
    {
        $wasHomePage = (bool) $page->is_home;

        $page->forceDelete();

        if ($wasHomePage) {
            $this->assignFallbackHomePage();
        }
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
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
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
            return;
        }

        Page::query()->where('is_home', true)->update(['is_home' => false]);
        $fallbackPage->forceFill(['is_home' => true])->saveQuietly();
    }
}