<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс отдает административного пользователя в стабильном формате.
 * Он помогает frontend-слою строить меню и проверки доступа без знания модели.
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * Ресурс собирает базовые данные пользователя, роли и разрешения.
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
        ];
    }
}