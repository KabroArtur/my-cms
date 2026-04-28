<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy держит базовые доступы для домена пользователей.
 * Он опирается на permission-ключи и защищает административный список пользователей.
 */
class UserPolicy
{
    protected function canManageProtectedUser(?User $user, User $target): bool
    {
        $protectedUsernames = collect(config('access.protected_usernames', []))
            ->filter(fn (mixed $username): bool => is_string($username) && $username !== '')
            ->values();

        if (! $protectedUsernames->contains($target->username)) {
            return true;
        }

        return $user?->hasRole('admin') ?? false;
    }

    /**
     * Policy разрешает просмотр списка пользователей.
     */
    public function viewAny(?User $user): bool
    {
        return $user?->hasPermission('users.access') ?? false;
    }

    /**
     * Policy разрешает создание пользователя.
     */
    public function create(?User $user): bool
    {
        return $user?->hasPermission('users.create') ?? false;
    }

    /**
     * Policy разрешает обновление пользователя.
     */
    public function update(?User $user, User $target): bool
    {
        return ($user?->hasPermission('users.update') ?? false)
            && $this->canManageProtectedUser($user, $target);
    }

    /**
     * Policy разрешает удаление пользователя.
     */
    public function delete(?User $user, User $target): bool
    {
        return ($user?->hasPermission('users.delete') ?? false)
            && $this->canManageProtectedUser($user, $target);
    }
}