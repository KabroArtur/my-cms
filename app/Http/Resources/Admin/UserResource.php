<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс отдает пользователя в стабильном формате для админки.
 * Он помогает UI выводить роли и разрешения без знания структуры модели.
 */
class UserResource extends JsonResource
{
    /**
     * Ресурс собирает базовые поля пользователя и его access-данные.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'roles' => $this->roleSlugs(),
            'permissions' => $this->permissionSlugs(),
            'two_factor_enabled' => $this->requiresTwoFactorChallenge(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}