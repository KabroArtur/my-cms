<?php

use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\AdditionalFieldGroup;
use App\Core\Pages\Models\AdditionalFieldValue;
use App\Core\Pages\Models\Page;
use App\Core\Pages\Services\AdditionalFieldsService;
use App\Core\Roles\Models\Permission;
use App\Core\Themes\Services\ThemeRuntime;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
});

it('creates field groups and stores additional values on page update', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', [
        'pages.access',
        'pages.update',
        'pages.create',
        'pages.additional_fields.manage',
    ])->pluck('id')->all());

    $page = Page::query()->create([
        'title' => 'Landing',
        'slug' => 'landing',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'home',
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($editor)
        ->postJson('/admin/api/field-groups', [
            'name' => 'Hero',
            'key' => 'hero_group',
            'description' => 'Hero fields',
            'location_rules' => [
                'rules' => [
                    ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                    ['field' => 'template', 'operator' => '=', 'value' => 'home'],
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
            'fields' => [
                [
                    'label' => 'Hero title',
                    'key' => 'hero_title',
                    'type' => 'text',
                    'settings' => [],
                    'default_value' => 'Default Hero',
                    'is_required' => false,
                    'sort_order' => 0,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.key', 'hero_group');

    $this->actingAs($editor)
        ->putJson('/admin/api/pages/'.$page->id, [
            'title' => 'Landing',
            'slug' => 'landing',
            'status' => 'published',
            'visibility' => 'public',
            'template' => 'home',
            'additional_fields' => [
                'hero_title' => 'Hero from editor',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.additional_fields.values.hero_title', 'Hero from editor');

    $storedValue = AdditionalFieldValue::query()
        ->where('entity_type', 'page')
        ->where('entity_id', $page->id)
        ->where('field_key', 'hero_title')
        ->first();

    expect($storedValue)->not->toBeNull();
    expect(json_decode((string) $storedValue?->value, true))->toBe('Hero from editor');
});

it('forbids managing field groups without dedicated permission', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', [
        'pages.access',
        'pages.update',
    ])->pluck('id')->all());

    $this->actingAs($editor)
        ->postJson('/admin/api/field-groups', [
            'name' => 'Forbidden Hero',
            'key' => 'forbidden_hero_group',
            'fields' => [],
        ])
        ->assertForbidden();

    expect(AdditionalFieldGroup::query()->where('key', 'forbidden_hero_group')->exists())->toBeFalse();
});

it('matches additional field groups by page, template and home-page rules', function (): void {
    $homePage = Page::query()->create([
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'default',
        'published_at' => now()->subMinute(),
        'is_home' => true,
    ]);

    $landingPage = Page::query()->create([
        'title' => 'Landing',
        'slug' => 'landing',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'landing',
        'published_at' => now()->subMinute(),
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'All pages',
        'key' => 'all_pages_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'Landing template only',
        'key' => 'landing_template_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'template', 'operator' => '=', 'value' => 'landing'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'Specific page only',
        'key' => 'specific_page_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'page_id', 'operator' => '=', 'value' => (string) $landingPage->id],
            ],
        ],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'Except home',
        'key' => 'except_home_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'is_home', 'operator' => '!=', 'value' => '1'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 3,
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'Home or landing',
        'key' => 'home_or_landing_group',
        'location_rules' => [
            'mode' => 'any',
            'rules' => [
                ['field' => 'is_home', 'operator' => '=', 'value' => '1'],
                ['field' => 'template', 'operator' => '=', 'value' => 'landing'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 4,
    ]);

    AdditionalFieldGroup::query()->create([
        'name' => 'Not landing page id',
        'key' => 'not_landing_page_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'page_id', 'operator' => 'not_in', 'value' => (string) $landingPage->id],
            ],
        ],
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $service = app(AdditionalFieldsService::class);

    $homeKeys = $service->resolveApplicableGroupsForPage($homePage)
        ->pluck('key')
        ->all();

    $landingKeys = $service->resolveApplicableGroupsForPage($landingPage)
        ->pluck('key')
        ->all();

    expect($homeKeys)->toContain('all_pages_group')
        ->and($homeKeys)->toContain('home_or_landing_group')
        ->and($homeKeys)->toContain('not_landing_page_group')
        ->and($homeKeys)->not->toContain('landing_template_group')
        ->and($homeKeys)->not->toContain('specific_page_group')
        ->and($homeKeys)->not->toContain('except_home_group')
        ->and($landingKeys)->toContain('all_pages_group')
        ->and($landingKeys)->toContain('home_or_landing_group')
        ->and($landingKeys)->toContain('landing_template_group')
        ->and($landingKeys)->toContain('specific_page_group')
        ->and($landingKeys)->toContain('except_home_group')
        ->and($landingKeys)->not->toContain('not_landing_page_group');
});

it('resolves field values through theme runtime with fallback defaults', function (): void {
    $page = Page::query()->create([
        'title' => 'Landing',
        'slug' => 'landing',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'landing',
        'published_at' => now()->subMinute(),
    ]);

    $group = AdditionalFieldGroup::query()->create([
        'name' => 'Hero',
        'key' => 'hero_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'landing'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $group->fields()->create([
        'label' => 'Hero title',
        'key' => 'hero_title',
        'type' => 'text',
        'settings' => [],
        'default_value' => json_encode('Default Hero', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'is_required' => false,
        'sort_order' => 0,
    ]);

    $runtime = app(ThemeRuntime::class)->usePage($page);

    expect($runtime->field('hero_title'))->toBe('Default Hero');

    AdditionalFieldValue::query()->create([
        'entity_type' => 'page',
        'entity_id' => $page->id,
        'field_key' => 'hero_title',
        'value' => json_encode('Hero Override', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    app(AdditionalFieldsService::class)->forgetEntityCache('page', $page->id);

    $freshRuntime = app(ThemeRuntime::class)->usePage($page->fresh());

    expect($freshRuntime->field('hero_title'))->toBe('Hero Override');
});
