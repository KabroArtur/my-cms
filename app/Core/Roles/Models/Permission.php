<?php

namespace App\Core\Roles\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Модель хранит отдельное разрешение административной системы.
 * Она связывает конкретные действия CMS с ролями и пользователями.
 */
#[Fillable(['name', 'slug'])]
class Permission extends Model
{
    /**
     * Разрешение может быть назначено многим ролям.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    /**
     * Разрешение может быть назначено напрямую многим пользователям.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user')
            ->withTimestamps();
    }
}