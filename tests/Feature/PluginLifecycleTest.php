<?php

use App\Core\Modules\Models\Plugin;
use App\Core\Modules\Services\PluginManager;
use App\Core\Roles\Models\Permission;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
});

it('manages blog plugin lifecycle through plugin manager', function (): void {
    $manager = app(PluginManager::class);

    $catalog = collect($manager->all())->keyBy('slug');
    expect($catalog->has('Blog'))->toBeTrue();
    expect($catalog->get('Blog')['status'])->toBe('not_installed');

    $manager->install('Blog');

    expect(Plugin::query()->where('slug', 'Blog')->value('status'))->toBe('installed');
    expect(Schema::hasTable('blog_posts'))->toBeFalse();

    $manager->enable('Blog');

    expect(Plugin::query()->where('slug', 'Blog')->value('status'))->toBe('enabled');
    expect(Schema::hasTable('blog_posts'))->toBeTrue();
    expect(Permission::query()->where('slug', 'blog.access')->exists())->toBeTrue();

    $manager->disable('Blog');

    expect(Plugin::query()->where('slug', 'Blog')->value('status'))->toBe('disabled');
    expect($manager->enabledRouteFiles('web'))->not->toContain(base_path('plugins/Blog/routes/web.php'));

    $manager->delete('Blog', true, false);

    expect(Plugin::query()->where('slug', 'Blog')->exists())->toBeFalse();
    expect(Schema::hasTable('blog_posts'))->toBeFalse();
    expect(Permission::query()->where('slug', 'blog.access')->exists())->toBeFalse();
});

it('exposes plugin api actions for authorized user', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()
        ->whereIn('slug', ['admin.access', 'plugins.access', 'plugins.install', 'plugins.enable', 'plugins.delete'])
        ->pluck('id')
        ->all());

    $this->actingAs($user)
        ->getJson('/admin/api/plugins')
        ->assertOk()
        ->assertJsonPath('items.0.slug', 'Blog');

    $this->actingAs($user)
        ->postJson('/admin/api/plugins/Blog/install')
        ->assertOk()
        ->assertJsonPath('plugin.status', 'installed');

    $this->actingAs($user)
        ->postJson('/admin/api/plugins/Blog/enable')
        ->assertOk()
        ->assertJsonPath('plugin.status', 'enabled');

    expect(Schema::hasTable('blog_posts'))->toBeTrue();

    $this->actingAs($user)
        ->postJson('/admin/api/plugins/Blog/disable')
        ->assertOk()
        ->assertJsonPath('plugin.status', 'disabled');

    $this->actingAs($user)
        ->deleteJson('/admin/api/plugins/Blog', [
            'drop_data' => true,
            'remove_files' => false,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Schema::hasTable('blog_posts'))->toBeFalse();
    expect(Plugin::query()->where('slug', 'Blog')->exists())->toBeFalse();
});

it('registers blog routes only while plugin is enabled after route reload', function (): void {
    $manager = app(PluginManager::class);
    $reloadRoutes = function (): void {
        $router = app('router');
        $router->setRoutes(new RouteCollection());

        require base_path('routes/web.php');

        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    };

    $manager->install('Blog');
    $manager->enable('Blog');

    Artisan::call('route:clear');
    $reloadRoutes();

    expect(Route::has('blog.index'))->toBeTrue();
    expect(Route::has('blog.show'))->toBeTrue();

    $manager->disable('Blog');

    Artisan::call('route:clear');
    $reloadRoutes();

    expect(Route::has('blog.index'))->toBeFalse();
    expect(Route::has('blog.show'))->toBeFalse();
});

it('manages generic manifest-only plugins for future extensions', function (): void {
    $slug = 'DemoNotes';
    $pluginPath = base_path('plugins/'.$slug);

    File::ensureDirectoryExists($pluginPath);
    File::put($pluginPath.'/plugin.json', json_encode([
        'slug' => $slug,
        'name' => 'Demo Notes',
        'description' => 'Manifest-only plugin for lifecycle checks.',
        'version' => '0.1.0',
        'permissions' => [],
        'admin_menu' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $manager = app(PluginManager::class);

        expect(collect($manager->all())->pluck('slug'))->toContain($slug);

        $manager->install($slug);
        expect(Plugin::query()->where('slug', $slug)->value('status'))->toBe('installed');

        $manager->enable($slug);
        expect(Plugin::query()->where('slug', $slug)->value('status'))->toBe('enabled');

        $manager->disable($slug);
        expect(Plugin::query()->where('slug', $slug)->value('status'))->toBe('disabled');

        $manager->delete($slug, false, true);
        expect(Plugin::query()->where('slug', $slug)->exists())->toBeFalse();
        expect(File::isDirectory($pluginPath))->toBeFalse();
    } finally {
        if (File::isDirectory($pluginPath)) {
            File::deleteDirectory($pluginPath);
        }
    }
});
