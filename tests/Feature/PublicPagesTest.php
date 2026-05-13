<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('shows a public page with the login slug', function () {
    Page::query()->create([
        'title' => 'Login page',
        'slug' => 'login',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Public login page content',
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login page')
        ->assertSee('Public login page content');
});

it('publishes a scheduled page when its date arrives', function () {
    $publicationDate = Carbon::parse('2026-04-27 15:00:00');

    Page::query()->create([
        'title' => 'Scheduled page',
        'slug' => 'scheduled-page',
        'status' => PageStatus::Scheduled,
        'visibility' => PageVisibility::Public,
        'content' => 'Scheduled page content',
        'published_at' => $publicationDate,
    ]);

    $this->travelTo($publicationDate->copy()->subMinute());

    $this->get('/scheduled-page')->assertNotFound();

    $this->travelTo($publicationDate->copy()->addMinute());

    $this->get('/scheduled-page')
        ->assertOk()
        ->assertSee('Scheduled page')
        ->assertSee('Scheduled page content');
});

it('shows nested pages by full url path', function () {
    $parent = Page::query()->create([
        'title' => 'Company',
        'slug' => 'company',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Company page content',
        'published_at' => now()->subMinute(),
    ]);

    $child = Page::query()->create([
        'title' => 'Nested page',
        'slug' => 'team',
        'parent_id' => $parent->id,
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Nested page content',
        'published_at' => now()->subMinute(),
    ]);

    expect($child->fresh()->path)->toBe('company/team');

    $this->get('/company/team')
        ->assertOk()
        ->assertSee('Nested page')
        ->assertSee('Nested page content');
});

it('renders site navigation from the same nested page tree', function () {
    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    $companyPage = Page::query()->create([
        'title' => 'Company',
        'slug' => 'company',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $teamPage = Page::query()->create([
        'title' => 'Team',
        'slug' => 'team',
        'parent_id' => $companyPage->id,
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Страницы сайта')
        ->assertSee('/company', false)
        ->assertSee('/company/team', false)
        ->assertSee('Company')
        ->assertSee('Team')
        ->assertDontSee('cms-menu__item', false);
});

it('moves a page between levels by changing only its parent', function () {
    $loginPage = Page::query()->create([
        'title' => 'Login',
        'slug' => 'login',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $reviewPage = Page::query()->create([
        'title' => 'Review',
        'slug' => 'review',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Review page content',
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/review')->assertOk();

    $reviewPage->update(['parent_id' => $loginPage->id]);

    $this->get('/review')->assertNotFound();
    $this->get('/login/review')
        ->assertOk()
        ->assertSee('Review page content');
});

it('renders featured media on public page', function () {
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
    ]);

    Page::query()->create([
        'title' => 'Featured page',
        'slug' => 'featured-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Featured page content',
        'featured_media_id' => $mediaFile->id,
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/featured-page')
        ->assertOk()
        ->assertSee('Featured page')
        ->assertSee('/storage/media/cover.jpg')
        ->assertSee('Featured page content');
});

it('publishes scheduled pages in database via artisan command', function () {
    $page = Page::query()->create([
        'title' => 'Scheduled command page',
        'slug' => 'scheduled-command-page',
        'status' => PageStatus::Scheduled,
        'visibility' => PageVisibility::Public,
        'content' => 'Scheduled command page content',
        'published_at' => now()->subMinute(),
    ]);

    $this->artisan('pages:publish-scheduled')
        ->expectsOutput('Опубликовано страниц: 1')
        ->assertExitCode(0);

    expect($page->fresh()->status)->toBe(PageStatus::Published);
});

it('renders the site specific 404 template for missing pages', function () {
    $this->get('/missing-page')
        ->assertNotFound()
        ->assertSee('Страница не найдена')
        ->assertSee('отдельный шаблон 404');
});

it('syncs the page tree and rebuilds paths through the repository', function () {
    $loginPage = Page::query()->create([
        'title' => 'Login',
        'slug' => 'login',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $reviewPage = Page::query()->create([
        'title' => 'Review',
        'slug' => 'review',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    app(PageRepository::class)->syncTree([
        ['id' => $loginPage->id, 'parent_id' => null, 'sort_order' => 0],
        ['id' => $reviewPage->id, 'parent_id' => $loginPage->id, 'sort_order' => 0],
    ]);

    expect($reviewPage->fresh()->path)->toBe('login/review');
});

it('allows moving the home page inside another branch', function () {
    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    $sectionPage = Page::query()->create([
        'title' => 'Section',
        'slug' => 'section',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    app(PageRepository::class)->syncTree([
        ['id' => $homePage->id, 'parent_id' => $sectionPage->id, 'sort_order' => 0],
        ['id' => $sectionPage->id, 'parent_id' => null, 'sort_order' => 0],
    ]);

    expect($homePage->fresh()->parent_id)->toBe($sectionPage->id);
});

it('allows nesting pages inside the home page', function () {
    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    $reviewPage = Page::query()->create([
        'title' => 'Review',
        'slug' => 'review',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    app(PageRepository::class)->syncTree([
        ['id' => $homePage->id, 'parent_id' => null, 'sort_order' => 0],
        ['id' => $reviewPage->id, 'parent_id' => $homePage->id, 'sort_order' => 0],
    ]);

    expect($reviewPage->fresh()->parent_id)->toBe($homePage->id);
});

it('returns 503 for public pages when emergency mode is enabled', function () {
    Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    Setting::query()->updateOrCreate(
        ['key' => 'security_emergency_mode'],
        ['value' => json_encode(true, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    );

    Setting::query()->updateOrCreate(
        ['key' => 'security_emergency_message'],
        ['value' => json_encode('Emergency maintenance window', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
    );

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('Emergency maintenance window');
});

it('renders page-level noindex and nofollow meta robots', function () {
    Page::query()->create([
        'title' => 'Private SEO page',
        'slug' => 'private-seo-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'seo_noindex' => true,
        'seo_nofollow' => true,
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/private-seo-page')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
});