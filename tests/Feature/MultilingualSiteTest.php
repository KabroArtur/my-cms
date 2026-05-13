<?php

use App\Core\Languages\Models\Language;
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

function multilingualAdmin(array $permissionSlugs): User
{
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(
        Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all(),
    );

    return $user;
}

it('keeps default home url without prefix and serves localized home on language prefix', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();
    $groupId = (string) str()->uuid();

    Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => $groupId,
        'title' => 'Головна',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Українська головна сторінка',
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => $groupId,
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'English home page',
        'is_home' => true,
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Українська головна сторінка')
        ->assertSee('<html lang="uk">', false)
        ->assertSee('hreflang="uk" href="https://my-cms.test/"', false)
        ->assertSee('hreflang="en" href="https://my-cms.test/en/"', false)
        ->assertDontSee('hreflang="x-default"', false);

    $this->get('/en')
        ->assertOk()
        ->assertSee('English home page')
        ->assertSee('<html lang="en">', false);

    $this->get('/uk')->assertNotFound();
});

it('renders translated page urls with hreflang links', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();
    $groupId = (string) str()->uuid();
    $baseUrl = rtrim((string) config('app.url'), '/');

    Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => $groupId,
        'title' => 'Про компанію',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Український контент',
        'published_at' => now()->subMinute(),
    ]);

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => $groupId,
        'title' => 'About',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'English content',
        'published_at' => now()->subMinute(),
    ]);

    $this->withServerVariables([
        'HTTP_HOST' => 'example.test',
        'HTTPS' => 'on',
    ])->get('/about')
        ->assertOk()
        ->assertSee('Український контент')
        ->assertSee('hreflang="uk"', false)
        ->assertSee('hreflang="en"', false)
        ->assertSee($baseUrl.'/about', false)
        ->assertSee($baseUrl.'/en/about', false);

    $this->withServerVariables([
        'HTTP_HOST' => 'example.test',
        'HTTPS' => 'on',
    ])->get('/en/about')
        ->assertOk()
        ->assertSee('English content')
        ->assertSee('<html lang="en">', false);
});

it('adds trailing slash to non-home canonical and hreflang urls when the setting is enabled', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();
    $groupId = (string) str()->uuid();
    $baseUrl = rtrim((string) config('app.url'), '/');

    app(SettingsManager::class)->update([
        'site_name' => 'Trailing slash CMS',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
        'home_page_id' => null,
        'site_theme' => 'default',
        'site_featured_media_variant' => 'original',
        'media_default_insert_variant' => 'original',
        'cms_palette' => 'slate',
        'seo_trailing_slash' => true,
    ]);

    Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => $groupId,
        'title' => 'Про компанію',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'Український контент',
        'published_at' => now()->subMinute(),
    ]);

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => $groupId,
        'title' => 'About',
        'slug' => 'about',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'content' => 'English content',
        'published_at' => now()->subMinute(),
    ]);

    $this->get('/about')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$baseUrl.'/about/">', false)
        ->assertSee('hreflang="uk" href="'.$baseUrl.'/about/"', false)
        ->assertSee('hreflang="en" href="'.$baseUrl.'/en/about/"', false);

    $this->get('/about/')
        ->assertOk()
        ->assertSee('Український контент');

    $this->get('/en/about/')
        ->assertOk()
        ->assertSee('English content');
});

it('filters admin page list by language', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();

    Page::query()->create([
        'language_id' => $defaultLanguage->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'Українська сторінка',
        'slug' => 'uk-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'English page',
        'slug' => 'en-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $admin = multilingualAdmin(['pages.access']);

    $this->actingAs($admin)
        ->getJson('/admin/api/pages?language_id='.$english->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.language.code', 'en')
        ->assertJsonPath('data.0.slug', 'en-page');
});

it('prevents deleting a language that still has pages', function (): void {
    $english = Language::query()->where('code', 'en')->firstOrFail();

    Page::query()->create([
        'language_id' => $english->id,
        'translation_group_id' => (string) str()->uuid(),
        'title' => 'English page',
        'slug' => 'locked-language-page',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $admin = multilingualAdmin(['settings.access', 'settings.general.manage']);

    $this->actingAs($admin)
        ->deleteJson('/admin/api/languages/'.$english->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors('language');
});

it('allows switching the default language from a language card update', function (): void {
    $defaultLanguage = Language::query()->where('code', 'uk')->firstOrFail();
    $english = Language::query()->where('code', 'en')->firstOrFail();
    $admin = multilingualAdmin(['settings.access', 'settings.general.manage']);

    $this->actingAs($admin)
        ->putJson('/admin/api/languages/'.$defaultLanguage->id, [
            'name' => $defaultLanguage->name,
            'native_name' => $defaultLanguage->native_name,
            'code' => $defaultLanguage->code,
            'locale' => $defaultLanguage->locale,
            'direction' => $defaultLanguage->direction,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => $defaultLanguage->sort_order,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $defaultLanguage->id)
        ->assertJsonPath('data.is_default', true);

    expect($defaultLanguage->fresh()->is_default)->toBeTrue();
    expect($english->fresh()->is_default)->toBeFalse();
});

it('falls back to the first created language when no default language remains', function (): void {
    $firstCreated = Language::query()->oldest('id')->firstOrFail();
    $otherLanguage = Language::query()->whereKeyNot($firstCreated->id)->firstOrFail();

    Language::query()->update(['is_default' => false]);

    app(\App\Core\Languages\Services\LanguageManager::class)->update($otherLanguage, [
        'name' => $otherLanguage->name,
        'native_name' => $otherLanguage->native_name,
        'code' => $otherLanguage->code,
        'locale' => $otherLanguage->locale,
        'direction' => $otherLanguage->direction,
        'is_default' => false,
        'is_active' => true,
        'sort_order' => $otherLanguage->sort_order,
    ]);

    expect($firstCreated->fresh()->is_default)->toBeTrue();
    expect($otherLanguage->fresh()->is_default)->toBeFalse();
});