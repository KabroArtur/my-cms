<?php

namespace Database\Seeders;

use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Сидер собирает базовую матрицу ролей и разрешений CMS.
 * Он создает стартовый доступ для локальной разработки и первого администратора.
 */
class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $configuredPermissions = collect(config('access.permissions', []));

        $permissions = $configuredPermissions
            ->mapWithKeys(function (string $slug): array {
                $permission = Permission::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => Str::headline(str_replace('.', ' ', $slug))],
                );

                return [$slug => $permission->id];
            });

        foreach (config('access.roles', []) as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $definition['name']],
            );

            $permissionIds = $definition['permissions'] === ['*']
                ? $permissions->values()->all()
                : collect($definition['permissions'])
                    ->map(fn (string $permissionSlug): ?int => $permissions->get($permissionSlug))
                    ->filter()
                    ->values()
                    ->all();

            $role->permissions()->sync($permissionIds);
        }

        $adminRole = Role::query()->where('slug', 'admin')->first();
        $adminUser = User::query()->where('username', 'admin')->first() ?? User::query()->first();

        if ($adminRole !== null && $adminUser !== null) {
            $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}