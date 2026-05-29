<?php

use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('clears access assignments and recreates emergency admin', function (): void {
    $adminRole = Role::query()->create([
        'name' => 'Temporary Admin',
        'slug' => 'admin',
    ]);

    $editorRole = Role::query()->create([
        'name' => 'Editor',
        'slug' => 'editor',
    ]);

    $pagesAccess = Permission::query()->create([
        'name' => 'Pages Access',
        'slug' => 'pages.access',
    ]);

    $usersAccess = Permission::query()->create([
        'name' => 'Users Access',
        'slug' => 'users.access',
    ]);

    $editorRole->permissions()->sync([$pagesAccess->id]);

    $legacyAdmin = User::factory()->create([
        'name' => 'Legacy Admin',
        'username' => 'admin',
        'email' => 'legacy-admin@example.com',
        'password' => 'legacy-password',
        'two_factor_channel' => 'email',
        'two_factor_enabled_at' => now(),
    ]);

    $legacyAdmin->roles()->sync([$editorRole->id]);
    $legacyAdmin->permissions()->sync([$usersAccess->id]);

    $otherUser = User::factory()->create();
    $otherUser->roles()->sync([$editorRole->id]);
    $otherUser->permissions()->sync([$pagesAccess->id]);

    $this->artisan('access:recover-admin')
        ->expectsOutput('Доступы очищены, базовые роли восстановлены, пользователь admin создан или обновлен.')
        ->assertSuccessful();

    $legacyAdmin->refresh();
    $otherUser->refresh();
    $adminRole->refresh();
    $editorRole->refresh();

    expect($legacyAdmin->email)->toBe('admin@example.com')
        ->and(Hash::check('admin', $legacyAdmin->password))->toBeTrue()
        ->and($legacyAdmin->roles()->pluck('slug')->all())->toBe(['admin'])
        ->and($legacyAdmin->permissions()->count())->toBe(0)
        ->and($legacyAdmin->two_factor_channel)->toBeNull()
        ->and($legacyAdmin->two_factor_enabled_at)->toBeNull()
        ->and($otherUser->roles()->count())->toBe(0)
        ->and($otherUser->permissions()->count())->toBe(0)
        ->and($adminRole->permissions()->pluck('slug')->sort()->values()->all())
            ->toBe(collect(config('access.permissions', []))->sort()->values()->all())
        ->and($editorRole->permissions()->pluck('slug')->sort()->values()->all())
            ->toBe(collect(config('access.roles.editor.permissions', []))->sort()->values()->all());
});
