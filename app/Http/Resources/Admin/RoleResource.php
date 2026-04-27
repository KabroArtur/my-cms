<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс отдает роль в стабильном формате для админки.
 * Он помогает UI показывать роль и ее разрешения без знания модели.
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'permissions' => $this->permissions->pluck('slug')->values()->all(),
            'users_count' => $this->users_count,
        ];
    }
}