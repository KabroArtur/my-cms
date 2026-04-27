<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Core\Auth\Models\TwoFactorChallenge;
use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'two_factor_channel', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_enabled_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Пользователь может состоять в нескольких ролях.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Пользователь может иметь прямые разрешения.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withTimestamps();
    }

    /**
     * Пользователь может иметь много одноразовых challenge второго фактора.
     */
    public function twoFactorChallenges(): HasMany
    {
        return $this->hasMany(TwoFactorChallenge::class);
    }

    /**
     * Пользователь проверяет наличие роли по slug.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles()
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * Пользователь проверяет наличие разрешения напрямую или через роль.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        $hasDirectPermission = $this->permissions()
            ->where('slug', $slug)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $slug))
            ->exists();
    }

    /**
     * Пользователь проверяет, есть ли доступ в административную зону.
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasRole('admin') || $this->roles()->exists() || $this->permissions()->exists();
    }

    /**
     * Пользователь сообщает, включен ли второй фактор.
     */
    public function requiresTwoFactorChallenge(): bool
    {
        if (! config('auth.two_factor.enabled', false)) {
            return false;
        }

        return $this->two_factor_enabled_at !== null && $this->two_factor_channel !== null;
    }

    /**
     * Пользователь возвращает список ролей для административного слоя.
     *
     * @return array<int, string>
     */
    public function roleSlugs(): array
    {
        return $this->roles
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Пользователь возвращает полный список разрешений.
     *
     * @return array<int, string>
     */
    public function permissionSlugs(): array
    {
        if ($this->hasRole('admin')) {
            return config('access.permissions', []);
        }

        $directPermissions = $this->permissions
            ->pluck('slug');

        $rolePermissions = $this->roles
            ->loadMissing('permissions')
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug');

        return $directPermissions
            ->merge($rolePermissions)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }
}
