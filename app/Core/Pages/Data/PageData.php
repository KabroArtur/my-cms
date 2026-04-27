<?php

namespace App\Core\Pages\Data;

use App\Core\Pages\Enums\PageStatus;
use Illuminate\Support\Str;

/**
 * DTO страницы переносит данные между слоями домена.
 * Он не хранит поведение и держит запись в предсказуемом формате.
 */
readonly class PageData
{
    public function __construct(
        public string $title,
        public string $slug,
        public PageStatus $status,
        public ?string $excerpt = null,
        public ?string $content = null,
        public ?string $template = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?int $parentId = null,
        public int $sortOrder = 0,
        public bool $isHome = false,
    ) {
    }

    /**
     * DTO собирается из входных данных и нормализует обязательные поля.
     */
    public static function fromArray(array $attributes): self
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $slug = trim((string) ($attributes['slug'] ?? ''));

        return new self(
            title: $title,
            slug: $slug !== '' ? $slug : Str::slug($title),
            status: isset($attributes['status'])
                ? PageStatus::from((string) $attributes['status'])
                : PageStatus::Draft,
            excerpt: self::nullableString($attributes['excerpt'] ?? null),
            content: self::nullableString($attributes['content'] ?? null),
            template: self::nullableString($attributes['template'] ?? null),
            metaTitle: self::nullableString($attributes['meta_title'] ?? null),
            metaDescription: self::nullableString($attributes['meta_description'] ?? null),
            parentId: isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null,
            sortOrder: isset($attributes['sort_order']) ? (int) $attributes['sort_order'] : 0,
            isHome: (bool) ($attributes['is_home'] ?? false),
        );
    }

    /**
     * DTO преобразуется в массив для модели и репозитория.
     * Поле публикации вычисляется из статуса страницы.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'template' => $this->template,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'parent_id' => $this->parentId,
            'sort_order' => $this->sortOrder,
            'is_home' => $this->isHome,
            'published_at' => $this->status === PageStatus::Published ? now() : null,
        ];
    }

    /**
     * DTO приводит пустые строки к null.
     */
    protected static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }
}