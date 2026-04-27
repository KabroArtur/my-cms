<?php

namespace App\Core\Pages\Repositories;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function findById(int $id): ?Page
    {
        return Page::query()->find($id);
    }

    public function findBySlug(string $slug): ?Page
    {
        return Page::query()
            ->where('slug', $slug)
            ->first();
    }

    public function create(PageData $data): Page
    {
        $attributes = $data->toArray();
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug);

        return Page::query()->create($attributes);
    }

    public function update(Page $page, PageData $data): Page
    {
        $attributes = $data->toArray();
        $attributes['slug'] = $this->resolveUniqueSlug($data->slug, $page->id);

        $page->fill($attributes);
        $page->save();

        return $page->fresh();
    }

    public function delete(Page $page): void
    {
        $page->delete();
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
}