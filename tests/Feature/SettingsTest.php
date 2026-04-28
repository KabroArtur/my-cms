<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Roles\Models\Permission;
use App\Core\Settings\Services\SettingsManager;
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

    $admin->permissions()->sync(Permission::query()->where('slug', 'settings.access')->pluck('id')->all());

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
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.site_name', 'Acme CMS')
        ->assertJsonPath('data.settings.home_page_id', $secondPage->id)
        ->assertJsonPath('data.settings.cms_palette', 'forest');

    expect($firstPage->fresh()->is_home)->toBeFalse();
    expect($secondPage->fresh()->is_home)->toBeTrue();
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
        ->assertSee('Editorial Theme')
        ->assertSee('Editorial page content')
        ->assertSee('Editorial CMS');
});