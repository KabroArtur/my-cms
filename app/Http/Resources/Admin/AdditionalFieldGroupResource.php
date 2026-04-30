<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalFieldGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
            'location_rules' => $this->location_rules ?? ['rules' => []],
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'fields' => AdditionalFieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
