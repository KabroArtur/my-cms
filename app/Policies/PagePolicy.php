<?php

namespace App\Policies;

use App\Core\Pages\Models\Page;
use App\Models\User;

/**
 * Policy держит базовые доступы для домена Pages.
 * Пока в локальной среде доступ открыт, а дальше правила можно ужесточить.
 */
class PagePolicy
{
    /**
     * Policy разрешает просмотр списка страниц.
     */
    public function viewAny(?User $user): bool
    {
        return $this->allows($user, 'pages.access');
    }

    /**
     * Policy разрешает создание страницы.
     */
    public function create(?User $user): bool
    {
        return $this->allows($user, 'pages.access');
    }

    /**
     * Policy разрешает обновление страницы.
     */
    public function update(?User $user, Page $page): bool
    {
        return $this->allows($user, 'pages.access');
    }

    /**
     * Policy разрешает удаление страницы.
     */
    public function delete(?User $user, Page $page): bool
    {
        return $this->allows($user, 'pages.access');
    }

    /**
     * Policy проверяет конкретное разрешение для домена Pages.
     */
    protected function allows(?User $user, string $permission): bool
    {
        return $user?->hasPermission($permission) ?? false;
    }
}