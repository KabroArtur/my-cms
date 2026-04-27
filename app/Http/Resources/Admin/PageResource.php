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
            'status' => $this->status?->value ?? $this->status,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'template' => $this->template,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_home' => $this->is_home,
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}