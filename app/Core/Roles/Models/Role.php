<?php

namespace App\Core\Roles\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Модель хранит роль административной системы.
 * Роль объединяет набор разрешений и назначается пользователям.
 */
#[Fillable(['name', 'slug'])]
class Role extends Model
{
    /**
     * Роль может быть назначена многим пользователям.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Роль содержит набор разрешений.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }
}