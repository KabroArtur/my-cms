<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $defaultValue = $this->default_value;

        if (is_string($defaultValue) && $defaultValue !== '') {
            $decoded = json_decode($defaultValue, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $defaultValue = $decoded;
            }
        }

        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'label' => $this->label,
            'key' => $this->key,
            'type' => $this->type,
            'settings' => $this->settings ?? [],
            'default_value' => $defaultValue,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
        ];
    }
}
