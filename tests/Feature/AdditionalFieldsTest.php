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

it('returns applicable field values using the requested template override', function (): void {
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
        'title' => 'Template switch',
        'slug' => 'template-switch',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'default',
        'published_at' => now()->subMinute(),
    ]);

    $defaultGroup = AdditionalFieldGroup::query()->create([
        'name' => 'Default fields',
        'key' => 'default_fields',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'default'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $homeGroup = AdditionalFieldGroup::query()->create([
        'name' => 'Home fields',
        'key' => 'home_fields',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'home'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    app(AdditionalFieldsService::class)->replaceGroupFields($defaultGroup, [
        [
            'label' => 'Default title',
            'key' => 'default_title',
            'type' => 'text',
            'settings' => [],
            'default_value' => 'Default title value',
        ],
    ]);

    app(AdditionalFieldsService::class)->replaceGroupFields($homeGroup, [
        [
            'label' => 'Home title',
            'key' => 'home_title',
            'type' => 'text',
            'settings' => [],
            'default_value' => 'Home title value',
        ],
        [
            'label' => 'Home hero image',
            'key' => 'home_hero_image',
            'type' => 'image',
            'settings' => [],
        ],
    ]);

    AdditionalFieldValue::query()->create([
        'entity_type' => 'page',
        'entity_id' => $page->id,
        'field_key' => 'home_title',
        'value' => json_encode('Draft home title', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    AdditionalFieldValue::query()->create([
        'entity_type' => 'page',
        'entity_id' => $page->id,
        'field_key' => 'home_hero_image',
        'value' => json_encode([
            'url' => '/uploads/home-hero.webp',
            'preview_url' => '/uploads/home-hero.webp',
            'title' => 'Draft home hero',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $this->actingAs($editor)
        ->getJson('/admin/api/field-groups/applicable?page_id='.$page->id.'&template=home')
        ->assertOk()
        ->assertJsonPath('data.groups.0.key', 'home_fields')
        ->assertJsonMissingPath('data.values.default_title')
        ->assertJsonPath('data.values.home_title', 'Draft home title')
        ->assertJsonPath('data.values.home_hero_image.url', '/uploads/home-hero.webp');
});

it('resolves field values through theme runtime with fallback defaults', function (): void {
    $page = Page::query()->create([
        'title' => 'Landing',
        'slug' => 'landing',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'home',
        'published_at' => now()->subMinute(),
    ]);

    $group = AdditionalFieldGroup::query()->create([
        'name' => 'Hero',
        'key' => 'hero_group',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'home'],
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

it('stores modern field types and preserves repeater order', function (): void {
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
        'title' => 'Home',
        'slug' => 'home',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'home',
        'published_at' => now()->subMinute(),
    ]);

    $group = AdditionalFieldGroup::query()->create([
        'name' => 'Flexible home',
        'key' => 'flexible_home',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'home'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    app(AdditionalFieldsService::class)->replaceGroupFields($group, [
        [
            'label' => 'Hero image',
            'key' => 'hero_image',
            'type' => 'image',
            'settings' => [],
        ],
        [
            'label' => 'Attachment',
            'key' => 'attachment_file',
            'type' => 'file',
            'settings' => [],
        ],
        [
            'label' => 'Gallery',
            'key' => 'hero_gallery',
            'type' => 'gallery',
            'settings' => [],
        ],
        [
            'label' => 'Theme',
            'key' => 'theme_variant',
            'type' => 'radio',
            'settings' => [
                'options' => [
                    ['label' => 'Light', 'value' => 'light'],
                    ['label' => 'Dark', 'value' => 'dark'],
                ],
            ],
        ],
        [
            'label' => 'Accent',
            'key' => 'accent_color',
            'type' => 'color',
            'settings' => [],
        ],
        [
            'label' => 'Launch date',
            'key' => 'launch_date',
            'type' => 'date',
            'settings' => [],
        ],
        [
            'label' => 'CTA URL',
            'key' => 'cta_url',
            'type' => 'url',
            'settings' => [],
        ],
        [
            'label' => 'CTA email',
            'key' => 'cta_email',
            'type' => 'email',
            'settings' => [],
        ],
        [
            'label' => 'Noindex',
            'key' => 'noindex',
            'type' => 'checkbox',
            'settings' => [],
        ],
        [
            'label' => 'Hero group',
            'key' => 'hero_group',
            'type' => 'group',
            'settings' => [
                'fields' => [
                    ['label' => 'Title', 'key' => 'title', 'type' => 'text', 'settings' => []],
                    ['label' => 'Subtitle', 'key' => 'subtitle', 'type' => 'textarea', 'settings' => []],
                    ['label' => 'Image', 'key' => 'image', 'type' => 'image', 'settings' => []],
                ],
            ],
        ],
        [
            'label' => 'FAQ',
            'key' => 'faq_items',
            'type' => 'repeater',
            'settings' => [
                'fields' => [
                    ['label' => 'Question', 'key' => 'question', 'type' => 'text', 'settings' => []],
                    ['label' => 'Answer', 'key' => 'answer', 'type' => 'textarea', 'settings' => []],
                ],
            ],
        ],
    ]);

    $payload = [
        'title' => 'Home',
        'slug' => 'home',
        'status' => 'published',
        'visibility' => 'public',
        'template' => 'home',
        'additional_fields' => [
            'hero_image' => ['url' => '/uploads/hero.webp', 'title' => 'Hero'],
            'attachment_file' => ['url' => '/uploads/spec.pdf', 'title' => 'Spec'],
            'hero_gallery' => [
                ['url' => '/uploads/one.webp', 'title' => 'One'],
                ['url' => '/uploads/two.webp', 'title' => 'Two'],
            ],
            'theme_variant' => 'dark',
            'accent_color' => '#ff6600',
            'launch_date' => '2026-05-01',
            'cta_url' => '/contacts',
            'cta_email' => 'info@example.test',
            'noindex' => true,
            'hero_group' => [
                'title' => 'Main title',
                'subtitle' => 'Main subtitle',
                'image' => [
                    'url' => '/uploads/group.webp',
                    'preview_url' => '/uploads/group.webp',
                    'title' => 'Group image',
                ],
            ],
            'faq_items' => [
                ['question' => 'Second', 'answer' => 'Answer 2'],
                ['question' => 'First', 'answer' => 'Answer 1'],
            ],
        ],
    ];

    $response = $this->actingAs($editor)
        ->putJson('/admin/api/pages/'.$page->id, $payload)
        ->assertOk();

    $values = $response->json('data.additional_fields.values');

    expect($values['theme_variant'])->toBe('dark')
        ->and($values['accent_color'])->toBe('#ff6600')
        ->and($values['launch_date'])->toBe('2026-05-01')
        ->and($values['cta_url'])->toBe('/contacts')
        ->and($values['cta_email'])->toBe('info@example.test')
        ->and($values['noindex'])->toBeTrue()
        ->and($values['hero_group']['title'])->toBe('Main title')
        ->and($values['hero_group']['image']['url'])->toBe('/uploads/group.webp')
        ->and($values['faq_items'][0]['question'])->toBe('Second')
        ->and($values['faq_items'][1]['question'])->toBe('First')
        ->and($values['hero_gallery'])->toHaveCount(2);

    $storedFaq = AdditionalFieldValue::query()
        ->where('entity_type', 'page')
        ->where('entity_id', $page->id)
        ->where('field_key', 'faq_items')
        ->first();

    expect(json_decode((string) $storedFaq?->value, true)[0]['question'])->toBe('Second');
});

it('validates required and invalid custom field values on page save', function (): void {
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
        'template' => 'landing',
        'published_at' => now()->subMinute(),
    ]);

    $group = AdditionalFieldGroup::query()->create([
        'name' => 'Landing fields',
        'key' => 'landing_fields',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'landing'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    app(AdditionalFieldsService::class)->replaceGroupFields($group, [
        [
            'label' => 'Hero title',
            'key' => 'hero_title',
            'type' => 'text',
            'is_required' => true,
            'settings' => [],
        ],
        [
            'label' => 'Theme',
            'key' => 'theme_variant',
            'type' => 'radio',
            'settings' => [
                'options' => [
                    ['label' => 'Light', 'value' => 'light'],
                    ['label' => 'Dark', 'value' => 'dark'],
                ],
            ],
        ],
        [
            'label' => 'Accent',
            'key' => 'accent_color',
            'type' => 'color',
            'settings' => [],
        ],
        [
            'label' => 'Launch date',
            'key' => 'launch_date',
            'type' => 'date',
            'settings' => [],
        ],
        [
            'label' => 'Contact URL',
            'key' => 'cta_url',
            'type' => 'url',
            'settings' => [],
        ],
        [
            'label' => 'Contact email',
            'key' => 'cta_email',
            'type' => 'email',
            'settings' => [],
        ],
    ]);

    $this->actingAs($editor)
        ->putJson('/admin/api/pages/'.$page->id, [
            'title' => 'Landing',
            'slug' => 'landing',
            'status' => 'published',
            'visibility' => 'public',
            'template' => 'landing',
            'additional_fields' => [
                'theme_variant' => 'blue',
                'accent_color' => 'orange',
                'launch_date' => 'not-a-date',
                'cta_url' => 'javascript:alert(1)',
                'cta_email' => 'broken-email',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'additional_fields.hero_title',
            'additional_fields.theme_variant',
            'additional_fields.accent_color',
            'additional_fields.launch_date',
            'additional_fields.cta_url',
            'additional_fields.cta_email',
        ]);
});

it('exposes custom field helpers and raw image urls through theme runtime', function (): void {
    $page = Page::query()->create([
        'title' => 'Landing',
        'slug' => 'landing-runtime',
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'template' => 'landing',
        'published_at' => now()->subMinute(),
    ]);

    $group = AdditionalFieldGroup::query()->create([
        'name' => 'Runtime fields',
        'key' => 'runtime_fields',
        'location_rules' => [
            'rules' => [
                ['field' => 'entity_type', 'operator' => '=', 'value' => 'page'],
                ['field' => 'template', 'operator' => '=', 'value' => 'landing'],
            ],
        ],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    app(AdditionalFieldsService::class)->replaceGroupFields($group, [
        [
            'label' => 'Hero title',
            'key' => 'hero_title',
            'type' => 'text',
            'settings' => [],
        ],
        [
            'label' => 'Hero image',
            'key' => 'hero_image',
            'type' => 'image',
            'settings' => [],
        ],
    ]);

    app(AdditionalFieldsService::class)->syncPageValues($page, [
        'hero_title' => 'Runtime title',
        'hero_image' => '/uploads/runtime-hero.webp',
    ]);

    $runtime = app(ThemeRuntime::class)->usePage($page->fresh());
    $data = $runtime->group('hero_group');

    expect($runtime->customField('hero_title'))->toBe('Runtime title')
        ->and($runtime->customFields()['hero_title'])->toBe('Runtime title')
        ->and($runtime->imageUrlFromValue('/uploads/runtime-hero.webp'))->toBe('/uploads/runtime-hero.webp')
        ->and($data->customFields())->toBeArray();
});
