<?php

namespace App\Core\Pages\Data;

use App\Core\Languages\Services\LanguageManager;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * DTO страницы переносит данные между слоями домена.
 * Он не хранит поведение и держит запись в предсказуемом формате.
 */
readonly class PageData
{
    public function __construct(
        public ?int $languageId,
        public ?string $translationGroupId,
        public string $title,
        public string $slug,
        public PageStatus $status,
        public PageVisibility $visibility,
        public ?string $excerpt = null,
        public ?string $content = null,
        public ?string $template = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public bool $seoNoindex = false,
        public bool $seoNofollow = false,
        public ?int $featuredMediaId = null,
        public ?int $parentId = null,
        public int $sortOrder = 0,
        public bool $isHome = false,
        public ?Carbon $publishedAt = null,
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
            languageId: isset($attributes['language_id']) && $attributes['language_id'] !== ''
                ? (int) $attributes['language_id']
                : app(LanguageManager::class)->defaultId(),
            translationGroupId: self::nullableString($attributes['translation_group_id'] ?? null),
            title: $title,
            slug: $slug !== '' ? $slug : Str::slug($title),
            status: isset($attributes['status'])
                ? PageStatus::from((string) $attributes['status'])
                : PageStatus::Draft,
            visibility: isset($attributes['visibility'])
                ? PageVisibility::from((string) $attributes['visibility'])
                : PageVisibility::Public,
            excerpt: self::nullableString($attributes['excerpt'] ?? null),
            content: self::nullableString($attributes['content'] ?? null),
            template: self::nullableString($attributes['template'] ?? null),
            metaTitle: self::nullableString($attributes['meta_title'] ?? null),
            metaDescription: self::nullableString($attributes['meta_description'] ?? null),
            seoNoindex: (bool) ($attributes['seo_noindex'] ?? false),
            seoNofollow: (bool) ($attributes['seo_nofollow'] ?? false),
            featuredMediaId: isset($attributes['featured_media_id']) && $attributes['featured_media_id'] !== ''
                ? (int) $attributes['featured_media_id']
                : null,
            parentId: isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null,
            sortOrder: isset($attributes['sort_order']) ? (int) $attributes['sort_order'] : 0,
            isHome: (bool) ($attributes['is_home'] ?? false),
            publishedAt: self::nullableDate($attributes['published_at'] ?? null),
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
        $publishedAt = match ($this->status) {
            PageStatus::Published => $this->publishedAt ?? now(),
            PageStatus::Scheduled => $this->publishedAt,
            default => null,
        };

        return [
            'language_id' => $this->languageId,
            'translation_group_id' => $this->translationGroupId,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'template' => $this->template,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'seo_noindex' => $this->seoNoindex,
            'seo_nofollow' => $this->seoNofollow,
            'featured_media_id' => $this->featuredMediaId,
            'parent_id' => $this->parentId,
            'sort_order' => $this->sortOrder,
            'is_home' => $this->isHome,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * DTO возвращает копию с уже очищенным HTML-контентом.
     */
    public function withContent(?string $content): self
    {
        return new self(
            languageId: $this->languageId,
            translationGroupId: $this->translationGroupId,
            title: $this->title,
            slug: $this->slug,
            status: $this->status,
            visibility: $this->visibility,
            excerpt: $this->excerpt,
            content: $content,
            template: $this->template,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            seoNoindex: $this->seoNoindex,
            seoNofollow: $this->seoNofollow,
            featuredMediaId: $this->featuredMediaId,
            parentId: $this->parentId,
            sortOrder: $this->sortOrder,
            isHome: $this->isHome,
            publishedAt: $this->publishedAt,
        );
    }

    /**
     * DTO приводит пустые строки к null.
     */
    protected static function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }

    /**
     * DTO приводит дату публикации к объекту Carbon.
     */
    protected static function nullableDate(mixed $value): ?Carbon
    {
        $value = self::nullableString($value);

        return $value === null ? null : Carbon::parse($value);
    }
}