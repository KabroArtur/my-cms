<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'path' => $this->path,
            'parent_id' => $this->parent_id,
            'children_count' => $this->children_count,
            'files_count' => $this->files_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}