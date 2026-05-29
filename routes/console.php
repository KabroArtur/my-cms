<?php

use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Media\Models\MediaFile;
use App\Core\Media\Services\MediaVariantManager;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pages:publish-scheduled', function () {
    $updatedCount = DB::table('pages')
        ->where('status', 'scheduled')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->update([
            'status' => 'published',
            'updated_at' => now(),
        ]);

    $this->info("Опубликовано страниц: {$updatedCount}");
})->purpose('Publishes scheduled pages whose publication date has arrived.');

Artisan::command('media:regenerate-variants {--id=*}', function (MediaVariantManager $variantsManager) {
    $ids = collect((array) $this->option('id'))
        ->filter(fn (mixed $value): bool => is_numeric($value))
        ->map(fn (mixed $value): int => (int) $value)
        ->values();

    $query = MediaFile::query();

    if ($ids->isNotEmpty()) {
        $query->whereKey($ids->all());
    }

    $files = $query->get();

    if ($files->isEmpty()) {
        $this->warn('Файлы для регенерации не найдены.');

        return self::SUCCESS;
    }

    $processedCount = 0;

    foreach ($files as $mediaFile) {
        $newVariants = $variantsManager->generateForMediaFile($mediaFile);

        $mediaFile->forceFill([
            'variants' => $newVariants,
        ])->save();

        $processedCount++;
    }

    $this->info("Регенерировано файлов: {$processedCount}");

    return self::SUCCESS;
})->purpose('Regenerates thumb, medium and large image variants for existing media files.');

Artisan::command('access:recover-admin {--username=admin} {--password=admin} {--email=admin@example.com} {--name=Administrator}', function () {
    $configuredPermissions = collect(config('access.permissions', []))
        ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
        ->values();

    if ($configuredPermissions->isEmpty()) {
        $this->error('В config/access.php не найдены permissions для восстановления доступа.');

        return self::FAILURE;
    }

    DB::transaction(function () use ($configuredPermissions): void {
        DB::table('permission_user')->delete();
        DB::table('role_user')->delete();
        DB::table('permission_role')->delete();

        $permissionIds = $configuredPermissions
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

            $rolePermissionIds = $definition['permissions'] === ['*']
                ? $permissionIds->values()->all()
                : collect($definition['permissions'])
                    ->map(fn (string $permissionSlug): ?int => $permissionIds->get($permissionSlug))
                    ->filter()
                    ->values()
                    ->all();

            $role->permissions()->sync($rolePermissionIds);
        }

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

        $username = (string) $this->option('username');
        $email = (string) $this->option('email');

        $user = User::query()->firstOrNew([
            'username' => $username,
        ]);

        if (User::query()
            ->where('email', $email)
            ->when($user->exists, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->exists()) {
            $email = sprintf('%s@recovery.local', $username);
        }

        $user->forceFill([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => Hash::make((string) $this->option('password')),
            'two_factor_channel' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        $user->permissions()->sync([]);
        $user->roles()->sync([$adminRole->id]);
    });

    $this->info('Доступы очищены, базовые роли восстановлены, пользователь admin создан или обновлен.');

    return self::SUCCESS;
})->purpose('Clears user access assignments, rebuilds base roles and permissions, and recreates the emergency admin user.');

Schedule::command('pages:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
