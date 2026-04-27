<?php

namespace App\Core\Pages\Models;

use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Модель страницы хранит базовую контентную запись CMS.
 * Она держит дерево страниц, публикацию и SEO-поля в одном месте.
 */
#[Fillable([
    'title',
    'slug',
    'status',
    'visibility',
    'excerpt',
    'content',
    'template',
    'meta_title',
    'meta_description',
    'parent_id',
    'sort_order',
    'is_home',
    'published_at',
])]
class Page extends Model
{
    use SoftDeletes;

    /**
     * Модель приводит поля к доменным типам.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'visibility' => PageVisibility::class,
            'is_home' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Страница может принадлежать родительской странице.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Страница может содержать дочерние страницы.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Scope выделяет опубликованные страницы для публичного слоя.
     */
    public function scopePublished(Builder $query): void
    {
        $query
            ->where('status', PageStatus::Published->value)
            ->whereNotNull('published_at');
    }
}