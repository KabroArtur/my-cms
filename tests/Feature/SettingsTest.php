<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Roles\Models\Permission;
use App\Core\Security\Services\AdminPathManager;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Settings\Models\Setting;
use App\Core\Support\Services\CmsCacheService;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
});

it('updates site settings and synchronizes the selected home page', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $firstPage = Page::query()->create([
        'title' => 'First',
        'slug' => 'first',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $secondPage = Page::query()->create([
        'title' => 'Second',
        'slug' => 'second',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $favicon = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'favicon.png',
        'original_name' => 'favicon.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size' => 1024,
        'width' => 128,
        'height' => 128,
        'path' => 'media/favicon.png',
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Acme CMS',
            'favicon_media_id' => $favicon->id,
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'home_page_id' => $secondPage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'large',
            'media_default_insert_variant' => 'medium',
            'cms_palette' => 'forest',
            'cache_data_enabled' => false,
            'cache_response_enabled' => true,
            'cache_response_ttl' => 120,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.site_name', 'Acme CMS')
        ->assertJsonPath('data.settings.home_page_id', $secondPage->id)
        ->assertJsonPath('data.settings.cms_palette', 'forest')
        ->assertJsonPath('data.settings.cache_data_enabled', false)
        ->assertJsonPath('data.settings.cache_response_enabled', true)
        ->assertJsonPath('data.settings.cache_response_ttl', 120);

    expect($firstPage->fresh()->is_home)->toBeFalse();
    expect($secondPage->fresh()->is_home)->toBeTrue();
});

it('allows saving settings without favicon', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'No Favicon CMS',
            'favicon_media_id' => '',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $homePage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.favicon_media_id', null)
        ->assertJsonPath('data.settings.site_name', 'No Favicon CMS');
});

it('renders public theme using configurable site settings', function (): void {
    $publishedAt = now()->subMinute()->startOfSecond();

    $mediaFile = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'cover.jpg',
        'original_name' => 'cover.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 4096,
        'width' => 1600,
        'height' => 900,
        'path' => 'media/cover.jpg',
        'variants' => [
            'large' => [
                'path' => 'media/large-cover.jpg',
                'width' => 1200,
                'height' => 675,
                'size' => 2500,
            ],
        ],
    ]);

    $page = Page::query()->create([
        'title' => 'Configured home',
        'slug' => 'configured-home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Configured page content',
        'featured_media_id' => $mediaFile->id,
        'published_at' => $publishedAt,
    ]);

    app(SettingsManager::class)->update([
        'site_name' => 'Configured CMS',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'home_page_id' => $page->id,
        'site_theme' => 'default',
        'site_featured_media_variant' => 'large',
        'media_default_insert_variant' => 'original',
        'cms_palette' => 'sand',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Configured CMS')
        ->assertSee('/storage/media/large-cover.jpg')
        ->assertSee($publishedAt->format('Y-m-d H:i:s'));
});

it('exposes discovered page templates in settings payload', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $this->actingAs($admin)
        ->getJson('/admin/api/settings')
        ->assertOk()
        ->assertJsonPath('data.options.page_templates.0.value', 'default')
        ->assertJsonFragment([
            'value' => 'home',
            'label' => 'Главная страница',
        ]);
});

it('renders page with selected theme template file', function (): void {
    $page = Page::query()->create([
        'title' => 'Configured home',
        'slug' => 'configured-home',
        'template' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Configured page content',
        'published_at' => now()->subMinute(),
    ]);

    app(SettingsManager::class)->update([
        'site_name' => 'Configured CMS',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'home_page_id' => $page->id,
        'site_theme' => 'default',
        'site_featured_media_variant' => 'original',
        'media_default_insert_variant' => 'original',
        'cms_palette' => 'sand',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Home template')
        ->assertSee('Этот шаблон можно выбрать прямо на странице');
});

it('switches public rendering to the selected site theme', function (): void {
    $page = Page::query()->create([
        'title' => 'Editorial page',
        'slug' => 'editorial-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Editorial page content',
        'published_at' => now()->subMinute(),
    ]);

    app(SettingsManager::class)->update([
        'site_name' => 'Editorial CMS',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
        'home_page_id' => $page->id,
        'site_theme' => 'editorial',
        'site_featured_media_variant' => 'original',
        'media_default_insert_variant' => 'original',
        'cms_palette' => 'slate',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Editorial page content')
        ->assertSee('Editorial CMS');
});

it('disables cache lookups when cache is turned off in settings', function (): void {
    Setting::query()->updateOrCreate(
        ['key' => 'cache_data_enabled'],
        ['value' => json_encode(false, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    );

    $cache = app(CmsCacheService::class);
    $calls = 0;

    $first = $cache->rememberSite('probe:disable-cache', function () use (&$calls): int {
        $calls++;

        return $calls;
    }, 300);

    $second = $cache->rememberSite('probe:disable-cache', function () use (&$calls): int {
        $calls++;

        return $calls;
    }, 300);

    expect($first)->toBe(1)
        ->and($second)->toBe(2)
        ->and($calls)->toBe(2);
});

it('forbids updating security settings without settings.security.manage permission', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->where('slug', 'settings.access')->pluck('id')->all());

    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Secured CMS',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $homePage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
            'admin_entry_path' => 'secure-panel',
        ])
        ->assertForbidden();
});

it('changes admin entry path and keeps old path redirect for a grace period', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Secured CMS',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $homePage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
            'admin_entry_path' => 'secure-panel',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.admin_entry_path', 'secure-panel')
        ->assertJsonPath('data.admin_path.current', 'secure-panel')
        ->assertJsonPath('data.admin_path.new_url_once', url('/secure-panel'));

    $encryptedValue = Setting::query()->where('key', 'admin_path_ciphertext')->value('value');
    $decoded = is_string($encryptedValue) ? json_decode($encryptedValue, true) : null;

    expect($decoded)->toBeString()
        ->and($decoded)->not->toContain('secure-panel')
        ->and(app(AdminPathManager::class)->currentPath())->toBe('secure-panel')
        ->and(app(AdminPathManager::class)->legacyPath())->toBe('admin');
});

it('rejects restricted admin entry path values', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Restricted CMS',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $homePage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
            'admin_entry_path' => 'api',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['admin_entry_path']);
});

it('allows setting admin entry path back to admin', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Admin Path Reset',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $homePage->id,
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
            'admin_entry_path' => 'admin',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.admin_entry_path', 'admin')
        ->assertJsonPath('data.admin_path.current', 'admin');
});