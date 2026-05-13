<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Languages\Models\Language;
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
            'seo_allow_indexing' => false,
            'seo_allow_following' => false,
            'seo_sitemap_enabled' => true,
            'seo_sitemap_change_frequency' => 'daily',
            'seo_robots_custom_rules' => 'Disallow: /admin/',
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
        ->assertJsonPath('data.settings.seo_allow_indexing', false)
        ->assertJsonPath('data.settings.seo_allow_following', false)
        ->assertJsonPath('data.settings.seo_sitemap_change_frequency', 'daily')
        ->assertJsonPath('data.settings.cms_palette', 'forest')
        ->assertJsonPath('data.settings.cache_data_enabled', false)
        ->assertJsonPath('data.settings.cache_response_enabled', true)
        ->assertJsonPath('data.settings.cache_response_ttl', 120);

    expect($firstPage->fresh()->is_home)->toBeFalse();
    expect($secondPage->fresh()->is_home)->toBeTrue();
});

it('updates home pages for each language independently', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['settings.access', 'settings.security.manage'])->pluck('id')->all());

    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();

    $defaultFirst = Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'Default first',
        'slug' => 'default-first',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $defaultSecond = Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'Default second',
        'slug' => 'default-second',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $englishFirst = Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'English first',
        'slug' => 'english-first',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $englishSecond = Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'English second',
        'slug' => 'english-second',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Per language homes',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => $defaultSecond->id,
            'home_page_ids' => [
                (string) $defaultLanguage->id => $defaultSecond->id,
                (string) $english->id => $englishSecond->id,
            ],
            'site_theme' => 'default',
            'site_featured_media_variant' => 'original',
            'media_default_insert_variant' => 'original',
            'cms_palette' => 'slate',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.home_page_id', $defaultSecond->id)
        ->assertJsonPath('data.settings.home_page_ids.'.$defaultLanguage->id, $defaultSecond->id)
        ->assertJsonPath('data.settings.home_page_ids.'.$english->id, $englishSecond->id);

    expect($defaultFirst->fresh()->is_home)->toBeFalse();
    expect($defaultSecond->fresh()->is_home)->toBeTrue();
    expect($englishFirst->fresh()->is_home)->toBeFalse();
    expect($englishSecond->fresh()->is_home)->toBeTrue();
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

it('renders fallback cover, social meta, hreflang and favicon links from settings', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();
    $translationGroup = (string) str()->uuid();

    $siteCover = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'site-cover.jpg',
        'original_name' => 'site-cover.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 4096,
        'width' => 1600,
        'height' => 900,
        'path' => 'media/site-cover.jpg',
        'title' => 'Site cover',
        'alt_text' => 'Global social cover',
    ]);

    $favicon = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'favicon.png',
        'original_name' => 'favicon.png',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'size' => 2048,
        'width' => 512,
        'height' => 512,
        'path' => 'media/favicon.png',
        'variants' => [
            'favicon-16' => ['path' => 'media/favicon-16.png', 'width' => 16, 'height' => 16, 'size' => 150],
            'favicon-32' => ['path' => 'media/favicon-32.png', 'width' => 32, 'height' => 32, 'size' => 220],
            'apple-touch-icon' => ['path' => 'media/apple-touch-icon.png', 'width' => 180, 'height' => 180, 'size' => 860],
            'android-chrome-192' => ['path' => 'media/android-chrome-192.png', 'width' => 192, 'height' => 192, 'size' => 980],
            'android-chrome-512' => ['path' => 'media/android-chrome-512.png', 'width' => 512, 'height' => 512, 'size' => 2048],
        ],
    ]);

    Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => $translationGroup,
        'title' => 'About',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'About page content',
        'published_at' => now()->subMinute(),
    ]);

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => $translationGroup,
        'title' => 'About EN',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'About page content EN',
        'published_at' => now()->subMinute(),
    ]);

    app(SettingsManager::class)->update([
        'site_name' => 'SEO CMS',
        'favicon_media_id' => $favicon->id,
        'site_default_featured_media_id' => $siteCover->id,
        'site_theme' => 'default',
        'site_featured_media_variant' => 'original',
        'media_default_insert_variant' => 'original',
        'seo_open_graph_enabled' => true,
        'seo_social_networks' => ['facebook', 'x', 'telegram'],
        'seo_hreflang_enabled' => true,
        'seo_favicon_enabled' => true,
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('/storage/media/site-cover.jpg')
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('/storage/media/site-cover.jpg', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('/storage/media/apple-touch-icon.png', false)
        ->assertSee('sizes="32x32"', false)
        ->assertSee('/storage/media/favicon-32.png', false);
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

it('updates media image optimization settings and exposes configured media variants', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->where('slug', 'settings.access')->pluck('id')->all());

    $response = $this->actingAs($admin)
        ->putJson('/admin/api/settings', [
            'site_name' => 'Image Settings CMS',
            'favicon_media_id' => null,
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'home_page_id' => null,
            'site_theme' => 'default',
            'seo_allow_indexing' => true,
            'seo_allow_following' => true,
            'seo_sitemap_enabled' => true,
            'seo_sitemap_change_frequency' => 'weekly',
            'site_featured_media_variant' => 'thumbnail',
            'media_default_insert_variant' => 'optimized',
            'media_image_optimize' => true,
            'media_image_max_width' => 2048,
            'media_image_max_height' => 1600,
            'media_image_jpg_quality' => 78,
            'media_image_webp_quality' => 74,
            'media_image_convert_to_webp' => true,
            'media_image_keep_original' => true,
            'media_image_create_thumbnails' => true,
            'theme_assets_obfuscate_js' => true,
            'theme_assets_obfuscation_preset' => 'aggressive',
            'cms_palette' => 'slate',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.media_image_optimize', true)
        ->assertJsonPath('data.settings.media_image_max_width', 2048)
        ->assertJsonPath('data.settings.media_image_max_height', 1600)
        ->assertJsonPath('data.settings.media_image_jpg_quality', 78)
        ->assertJsonPath('data.settings.media_image_webp_quality', 74)
        ->assertJsonPath('data.settings.media_image_convert_to_webp', true)
        ->assertJsonPath('data.settings.media_image_keep_original', true)
        ->assertJsonPath('data.settings.media_image_create_thumbnails', true)
        ->assertJsonPath('data.settings.theme_assets_obfuscate_js', true)
        ->assertJsonPath('data.settings.theme_assets_obfuscation_preset', 'aggressive')
        ->assertJsonFragment([
            'value' => 'optimized',
            'label' => 'Optimized',
        ])
        ->assertJsonFragment([
            'value' => 'thumbnail',
            'label' => 'Thumbnail (300x300) · CROP',
        ])
        ->assertJsonFragment([
            'value' => 'aggressive',
            'label' => 'Aggressive',
        ])
        ->assertJsonFragment([
            'value' => 'weekly',
            'label' => 'Еженедельно',
        ]);

    expect(app(SettingsManager::class)->all()['media_image_max_width'])->toBe(2048);
    expect(app(SettingsManager::class)->all()['theme_assets_obfuscation_preset'])->toBe('aggressive');
    expect($response->json('data.options.media_variants'))->toBeArray();
});

it('renders dynamic robots and sitemap from seo settings', function (): void {
    config()->set('app.url', 'http://example.test');
    config()->set('app.enforce_canonical_url', false);

    $home = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $article = Page::query()->create([
        'title' => 'About',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'seo_noindex' => true,
        'seo_nofollow' => true,
        'published_at' => now()->subMinute(),
    ]);

    app(SettingsManager::class)->update([
        'site_name' => 'SEO CMS',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
        'home_page_id' => $home->id,
        'site_theme' => 'default',
        'seo_allow_indexing' => true,
        'seo_allow_following' => false,
        'seo_sitemap_enabled' => true,
        'seo_sitemap_change_frequency' => 'monthly',
        'seo_canonical_scheme' => 'https',
        'seo_canonical_www_mode' => 'with_www',
        'seo_sitemap_excluded_paths' => "about",
        'seo_robots_custom_rules' => 'Disallow: /admin/',
        'site_featured_media_variant' => 'original',
        'media_default_insert_variant' => 'original',
        'cms_palette' => 'slate',
    ]);

    $this->withServerVariables([
        'HTTP_HOST' => 'www.example.test',
        'HTTPS' => 'on',
    ])->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Disallow:', false)
        ->assertSee('Disallow: /admin/', false)
        ->assertSee('Sitemap: https://www.example.test/sitemap.xml', false);

    $this->withServerVariables([
        'HTTP_HOST' => 'www.example.test',
        'HTTPS' => 'on',
    ])->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee('<loc>https://www.example.test/sitemaps/pages-1.xml</loc>', false);

    $this->withServerVariables([
        'HTTP_HOST' => 'www.example.test',
        'HTTPS' => 'on',
    ])->get('/sitemaps/pages-1.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<changefreq>monthly</changefreq>', false)
        ->assertSee('<loc>https://www.example.test/</loc>', false)
        ->assertDontSee('<loc>https://www.example.test/about</loc>', false);

    $this->withServerVariables([
        'HTTP_HOST' => 'www.example.test',
        'HTTPS' => 'on',
    ])->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="index,nofollow">', false);
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
        ->assertSee('Configured home')
        ->assertSee('Configured page content');
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