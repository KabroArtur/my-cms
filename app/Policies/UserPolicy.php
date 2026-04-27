<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy держит базовые доступы для домена пользователей.
 * Он опирается на permission-ключи и защищает административный список пользователей.
 */
class UserPolicy
{
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
        return $user?->hasPermission('users.access') ?? false;
    }

    /**
     * Policy разрешает обновление пользователя.
     */
    public function update(?User $user, User $target): bool
    {
        return $user?->hasPermission('users.access') ?? false;
    }

    /**
     * Policy разрешает удаление пользователя.
     */
    public function delete(?User $user, User $target): bool
    {
        return $user?->hasPermission('users.access') ?? false;
    }
}