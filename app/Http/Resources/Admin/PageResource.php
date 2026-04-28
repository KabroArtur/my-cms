<?php

namespace App\Http\Resources\Admin;

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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path,
            'public_url' => $this->is_home ? '/' : '/'.$this->path,
            'status' => $this->status?->value ?? $this->status,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'template' => $this->template,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'parent_id' => $this->parent_id,
            'parent_title' => $this->parent?->title,
            'sort_order' => $this->sort_order,
            'is_home' => $this->is_home,
            'published_at' => $this->published_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}