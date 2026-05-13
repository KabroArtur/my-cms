<?php

namespace App\Http\Resources\Admin;

use App\Core\Languages\Services\LanguageManager;
use App\Core\Pages\Models\Page;
use App\Core\Pages\Services\AdditionalFieldsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс отдает страницу в стабильном формате для админки.
 * Он удерживает API-ответ отдельно от структуры модели.
 */
class PageResource extends JsonResource
{
    /**
     * Ресурс собирает поля страницы для frontend-слоя.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSinglePageResponse = $request->route('page') instanceof Page;

        return [
            'id' => $this->id,
            'language_id' => $this->language_id,
            'translation_group_id' => $this->translation_group_id,
            'language' => $this->language === null
                ? null
                : [
                    'id' => $this->language->id,
                    'code' => $this->language->code,
                    'locale' => $this->language->locale,
                    'name' => $this->language->name,
                    'native_name' => $this->language->native_name,
                    'direction' => $this->language->direction,
                    'is_default' => (bool) $this->language->is_default,
                ],
            'created_by' => $this->created_by,
            'creator' => $this->creator === null
                ? null
                : [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'username' => $this->creator->username,
                ],
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path,
            'public_url' => app(LanguageManager::class)->pageRelativeUrl($this->resource),
            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'template' => $this->template,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'seo_noindex' => (bool) $this->seo_noindex,
            'seo_nofollow' => (bool) $this->seo_nofollow,
            'featured_media_id' => $this->featured_media_id,
            'featured_media' => $this->featuredMedia ? MediaFileResource::make($this->featuredMedia) : null,
            'parent_id' => $this->parent_id,
            'parent_title' => $this->parent?->title,
            'sort_order' => $this->sort_order,
            'is_home' => $this->is_home,
            'published_at' => $this->published_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
            $this->mergeWhen($isSinglePageResponse, function (): array {
                $additionalFields = app(AdditionalFieldsService::class);

                return [
                    'additional_fields' => [
                        'groups' => AdditionalFieldGroupResource::collection(
                            $additionalFields->resolveApplicableGroupsForPage($this->resource)->load('fields'),
                        )->resolve(),
                        'values' => $additionalFields->combinedValuesForPage($this->resource),
                        'translations' => self::collection(
                            Page::query()
                                ->with('language')
                                ->where('translation_group_id', $this->translation_group_id)
                                ->whereKeyNot($this->id)
                                ->get()
                        )->resolve(),
                    ],
                ];
            }),
        ];
    }
}