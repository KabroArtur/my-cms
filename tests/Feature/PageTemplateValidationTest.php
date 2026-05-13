<?php

use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Roles\Models\Permission;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
});

it('rejects unknown template on page create', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', [
        'pages.access',
        'pages.create',
    ])->pluck('id')->all());

    $this->actingAs($editor)
        ->postJson('/admin/api/pages', [
            'title' => 'Template validation',
            'slug' => 'template-validation',
            'status' => 'published',
            'visibility' => 'public',
            'template' => 'unknown-template',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['template']);
});

it('accepts discovered template on page update', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', [
        'pages.access',
        'pages.create',
        'pages.update',
    ])->pluck('id')->all());

    $page = Page::query()->create([
        'title' => 'Template source page',
        'slug' => 'template-source-page',
        'template' => null,
        'status' => PageStatus::Published,
        'visibility' => PageVisibility::Public,
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($editor)
        ->putJson('/admin/api/pages/'.$page->id, [
            'title' => 'Template source page',
            'slug' => 'template-source-page',
            'status' => 'published',
            'visibility' => 'public',
            'template' => 'home',
            'seo_noindex' => true,
            'seo_nofollow' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.template', 'home')
        ->assertJsonPath('data.seo_noindex', true)
        ->assertJsonPath('data.seo_nofollow', true);
});
