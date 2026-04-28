<?php

namespace App\Policies;

use App\Core\Roles\Models\Role;
use App\Models\User;

/**
 * Policy держит базовые доступы для домена ролей.
 * Он опирается на permission-ключи административной системы.
 */
class RolePolicy
{
    protected function canManageProtectedRole(?User $user, Role $role): bool
    {
        $protectedRoles = collect(config('access.protected_roles', []))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values();

        if (! $protectedRoles->contains($role->slug)) {
            return true;
        }

        return $user?->hasRole('admin') ?? false;
    }

    /**
     * Policy разрешает просмотр списка ролей.
     */
    public function viewAny(?User $user): bool
    {
        return $user?->hasPermission('roles.access') ?? false;
    }

    /**
     * Policy разрешает создание роли.
     */
    public function create(?User $user): bool
    {
        return $user?->hasPermission('roles.create') ?? false;
    }

    /**
     * Policy разрешает обновление роли.
     */
    public function update(?User $user, Role $role): bool
    {
        return ($user?->hasPermission('roles.update') ?? false)
            && $this->canManageProtectedRole($user, $role);
    }
}