<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Pages\Enums\PageStatus;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Auth\Services\TwoFactorChallengeService;
use App\Http\Middleware\RedirectToCanonicalUrl;
use App\Http\Middleware\SetSecurityHeaders;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
});

it('locks two factor verification after too many invalid attempts', function (): void {
    Notification::fake();

    config()->set('auth.two_factor.enabled', true);
    config()->set('auth.two_factor.verify.max_attempts', 2);
    config()->set('auth.two_factor.verify.decay_seconds', 300);

    $user = User::factory()->create([
        'password' => 'StrongPass123',
        'two_factor_channel' => 'email',
        'two_factor_enabled_at' => now(),
    ]);

    app(TwoFactorChallengeService::class)->issue($user);

    $this->actingAs($user)
        ->post('/admin/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'Неверный или просроченный код.']);

    $this->actingAs($user)
        ->post('/admin/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'Неверный или просроченный код.']);

    $this->actingAs($user)
        ->post('/admin/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(session('errors')->first('code'))->toContain('Слишком много неверных попыток');
});

it('writes audit log when admin access is denied after password authentication', function (): void {
    Log::shouldReceive('channel')
        ->once()
        ->with('security')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with('cms.audit', \Mockery::on(function (array $context): bool {
            return ($context['action'] ?? null) === 'auth.admin_access_denied'
                && ($context['context']['login'] ?? null) === 'limited-user';
        }));

    $limitedRole = Role::query()->create([
        'name' => 'Limited Auth',
        'slug' => 'limited-auth',
    ]);

    $user = User::factory()->create([
        'username' => 'limited-user',
        'password' => 'StrongPass123',
    ]);

    $user->roles()->sync([$limitedRole->id]);

    $this->post('/admin/login', [
        'login' => 'limited-user',
        'password' => 'StrongPass123',
    ])->assertSessionHasErrors(['login' => 'Доступ в административную систему запрещен.']);
});

it('writes audit log when two factor resend is throttled', function (): void {
    Notification::fake();

    config()->set('auth.two_factor.enabled', true);
    config()->set('auth.two_factor.resend.cooldown_seconds', 60);

    Log::shouldReceive('channel')
        ->once()
        ->with('security')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with('cms.audit', \Mockery::on(function (array $context): bool {
            return ($context['action'] ?? null) === 'auth.two_factor_resend_throttled'
                && ($context['retry_after'] ?? null) === null
                && (($context['context']['retry_after'] ?? 0) > 0);
        }));

    $user = User::factory()->create([
        'password' => 'StrongPass123',
        'two_factor_channel' => 'email',
        'two_factor_enabled_at' => now(),
    ]);

    app(TwoFactorChallengeService::class)->issue($user);

    Notification::fake();

    $this->actingAs($user)
        ->post('/admin/two-factor-challenge/resend')
        ->assertSessionHasErrors('code');
});

it('throttles two factor resend requests during cooldown', function (): void {
    Notification::fake();

    config()->set('auth.two_factor.enabled', true);
    config()->set('auth.two_factor.resend.cooldown_seconds', 60);

    $user = User::factory()->create([
        'password' => 'StrongPass123',
        'two_factor_channel' => 'email',
        'two_factor_enabled_at' => now(),
    ]);

    app(TwoFactorChallengeService::class)->issue($user);

    Notification::fake();

    $this->actingAs($user)
        ->post('/admin/two-factor-challenge/resend')
        ->assertSessionHasErrors('code');

    expect(session('errors')->first('code'))->toContain('Повторная отправка будет доступна через');
    Notification::assertNothingSent();
});

it('forbids assigning roles through users api without roles permission', function (): void {
    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->where('slug', 'users.update')->pluck('id')->all());

    $target = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $this->actingAs($manager)
        ->putJson("/admin/api/users/{$target->id}", [
            'name' => $target->name,
            'username' => $target->username,
            'email' => $target->email,
            'password' => '',
            'role_slugs' => ['admin'],
        ])
        ->assertForbidden();

    expect($target->fresh()->roleSlugs())->not->toContain('admin');
});

it('rejects weak passwords for admin users api', function (): void {
    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin->permissions()->sync(Permission::query()->whereIn('slug', ['users.access', 'users.create'])->pluck('id')->all());

    $this->actingAs($admin)
        ->postJson('/admin/api/users', [
            'name' => 'Weak User',
            'username' => 'weak-user',
            'email' => 'weak@example.com',
            'password' => '1234',
            'role_slugs' => ['editor'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('denies admin api access for user with role without cms permissions', function (): void {
    $limitedRole = Role::query()->create([
        'name' => 'Limited',
        'slug' => 'limited',
    ]);

    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->roles()->sync([$limitedRole->id]);

    $this->actingAs($user)
        ->getJson('/admin/api/me')
        ->assertForbidden();
});

it('forbids non admin from updating protected admin user', function (): void {
    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->whereIn('slug', ['users.access', 'users.update'])->pluck('id')->all());

    $admin = User::factory()->create([
        'username' => 'admin',
        'password' => 'StrongPass123',
    ]);

    $admin->roles()->sync(Role::query()->where('slug', 'admin')->pluck('id')->all());

    $this->actingAs($manager)
        ->putJson("/admin/api/users/{$admin->id}", [
            'name' => 'Changed Admin',
            'username' => 'admin',
            'email' => $admin->email,
            'password' => '',
            'role_slugs' => ['admin'],
        ])
        ->assertForbidden();

    expect($admin->fresh()->name)->not->toBe('Changed Admin');
});

it('forbids non admin from deleting protected admin user', function (): void {
    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->whereIn('slug', ['users.access', 'users.delete'])->pluck('id')->all());

    $admin = User::factory()->create([
        'username' => 'admin',
        'password' => 'StrongPass123',
    ]);

    $admin->roles()->sync(Role::query()->where('slug', 'admin')->pluck('id')->all());

    $this->actingAs($manager)
        ->deleteJson("/admin/api/users/{$admin->id}")
        ->assertForbidden();

    expect($admin->fresh())->not->toBeNull();
});

it('forbids non admin from updating protected system role', function (): void {
    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->whereIn('slug', ['roles.access', 'roles.update'])->pluck('id')->all());

    $editorRole = Role::query()->where('slug', 'editor')->firstOrFail();

    $this->actingAs($manager)
        ->putJson("/admin/api/roles/{$editorRole->id}", [
            'name' => 'Editor Updated',
            'slug' => 'editor',
            'permission_slugs' => ['pages.access'],
        ])
        ->assertForbidden();

    expect($editorRole->fresh()->name)->not->toBe('Editor Updated');
});

it('denies creating users without explicit users.create permission', function (): void {
    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->where('slug', 'users.access')->pluck('id')->all());

    $this->actingAs($manager)
        ->postJson('/admin/api/users', [
            'name' => 'No Create',
            'username' => 'no-create',
            'email' => 'no-create@example.com',
            'password' => 'StrongPass123',
            'role_slugs' => [],
        ])
        ->assertForbidden();
});

it('denies creating pages without explicit pages.create permission', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->where('slug', 'pages.access')->pluck('id')->all());

    $this->actingAs($editor)
        ->postJson('/admin/api/pages', [
            'title' => 'No Create Page',
            'slug' => 'no-create-page',
            'status' => PageStatus::Draft->value,
            'visibility' => PageVisibility::Public->value,
        ])
        ->assertForbidden();
});

it('assigns page creator on create and exposes creator in admin api response', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.create'])->pluck('id')->all());

    $response = $this->actingAs($editor)
        ->postJson('/admin/api/pages', [
            'title' => 'Owned Page',
            'slug' => 'owned-page',
            'status' => PageStatus::Draft->value,
            'visibility' => PageVisibility::Public->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.created_by', $editor->id)
        ->assertJsonPath('data.creator.id', $editor->id)
        ->assertJsonPath('data.creator.username', $editor->username);

    $page = Page::query()->findOrFail($response->json('data.id'));

    expect($page->created_by)->toBe($editor->id);
});

it('allows page update and delete only for owner or admin', function (): void {
    $owner = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $otherEditor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $admin = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $owner->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.update', 'pages.delete'])->pluck('id')->all());
    $otherEditor->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.update', 'pages.delete'])->pluck('id')->all());

    $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $page = Page::query()->create([
        'created_by' => $owner->id,
        'title' => 'Owner Page',
        'slug' => 'owner-page',
        'status' => PageStatus::Draft,
        'visibility' => PageVisibility::Public,
    ]);

    $this->actingAs($otherEditor)
        ->putJson("/admin/api/pages/{$page->id}", [
            'title' => 'Other Editor Update',
            'slug' => 'owner-page',
            'status' => PageStatus::Draft->value,
            'visibility' => PageVisibility::Public->value,
        ])
        ->assertForbidden();

    $this->actingAs($otherEditor)
        ->deleteJson("/admin/api/pages/{$page->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->putJson("/admin/api/pages/{$page->id}", [
            'title' => 'Owner Updated',
            'slug' => 'owner-page',
            'status' => PageStatus::Draft->value,
            'visibility' => PageVisibility::Public->value,
        ])
        ->assertOk();

    expect($page->fresh()->title)->toBe('Owner Updated');

    $this->actingAs($admin)
        ->deleteJson("/admin/api/pages/{$page->id}")
        ->assertNoContent();

    expect($page->fresh()->deleted_at)->not->toBeNull();
});

it('sanitizes page html content before storing and rendering it publicly', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.create'])->pluck('id')->all());

    $response = $this->actingAs($editor)
        ->postJson('/admin/api/pages', [
            'title' => 'Safe Content Page',
            'slug' => 'safe-content-page',
            'status' => PageStatus::Published->value,
            'visibility' => PageVisibility::Public->value,
            'content' => '<p>Hello <strong>world</strong></p><script>alert(1)</script><img src="/image.jpg" onerror="alert(1)"><a href="javascript:alert(1)">bad</a>',
        ])
        ->assertCreated();

    $pageId = $response->json('data.id');
    $page = Page::query()->findOrFail($pageId);

    expect($page->content)->toContain('<p>Hello <strong>world</strong></p>');
    expect($page->content)->not->toContain('<script');
    expect($page->content)->not->toContain('onerror');
    expect($page->content)->not->toContain('javascript:');

    $this->get('/safe-content-page')
        ->assertOk()
        ->assertSee('<strong>world</strong>', false)
        ->assertDontSee('<script', false)
        ->assertDontSee('onerror', false)
        ->assertDontSee('javascript:', false);
});

it('allows reordering pages with pages.update without pages.create', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.update'])->pluck('id')->all());

    $firstPage = Page::query()->create([
        'title' => 'First Page',
        'slug' => 'first-page',
        'status' => PageStatus::Draft,
        'visibility' => PageVisibility::Public,
    ]);

    $secondPage = Page::query()->create([
        'title' => 'Second Page',
        'slug' => 'second-page',
        'status' => PageStatus::Draft,
        'visibility' => PageVisibility::Public,
    ]);

    $this->actingAs($editor)
        ->putJson('/admin/api/pages-tree', [
            'tree' => [
                ['id' => $firstPage->id, 'parent_id' => null, 'sort_order' => 1],
                ['id' => $secondPage->id, 'parent_id' => null, 'sort_order' => 0],
            ],
        ])
        ->assertNoContent();

    expect($firstPage->fresh()->sort_order)->toBe(1);
    expect($secondPage->fresh()->sort_order)->toBe(0);
});

it('stores featured media on pages through admin api', function (): void {
    $editor = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $editor->permissions()->sync(Permission::query()->whereIn('slug', ['pages.access', 'pages.create', 'pages.update'])->pluck('id')->all());

    $mediaFile = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'hero.jpg',
        'original_name' => 'hero.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
        'width' => 1280,
        'height' => 720,
        'path' => 'media/hero.jpg',
    ]);

    $response = $this->actingAs($editor)
        ->postJson('/admin/api/pages', [
            'title' => 'Media Page',
            'slug' => 'media-page',
            'status' => PageStatus::Published->value,
            'visibility' => PageVisibility::Public->value,
            'featured_media_id' => $mediaFile->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.featured_media_id', $mediaFile->id)
        ->assertJsonPath('data.featured_media.original_name', 'hero.jpg');

    $page = Page::query()->findOrFail($response->json('data.id'));

    expect($page->featured_media_id)->toBe($mediaFile->id);

    $this->actingAs($editor)
        ->putJson("/admin/api/pages/{$page->id}", [
            'title' => 'Media Page',
            'slug' => 'media-page',
            'status' => PageStatus::Published->value,
            'visibility' => PageVisibility::Public->value,
            'featured_media_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.featured_media_id', null);

    expect($page->fresh()->featured_media_id)->toBeNull();
});

it('writes audit log for admin user creation', function (): void {
    Log::shouldReceive('channel')
        ->once()
        ->with('security')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->with('cms.audit', \Mockery::on(function (array $context): bool {
            return ($context['action'] ?? null) === 'users.created'
                && ($context['actor_id'] ?? null) !== null
                && ($context['context']['target_username'] ?? null) === 'audited-user';
        }));

    $manager = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $manager->permissions()->sync(Permission::query()->whereIn('slug', ['users.access', 'users.create'])->pluck('id')->all());

    $this->actingAs($manager)
        ->postJson('/admin/api/users', [
            'name' => 'Audited User',
            'username' => 'audited-user',
            'email' => 'audited@example.com',
            'password' => 'StrongPass123',
            'role_slugs' => [],
        ])
        ->assertCreated();
});

it('adds baseline security headers to auth responses', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->assertHeader('Content-Security-Policy');

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'");
    expect($policy)->toContain("object-src 'none'");
    expect($policy)->toContain("frame-ancestors 'self'");
    expect($policy)->toContain("form-action 'self'");
    expect($policy)->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('allows vite dev server origin in csp when hot file exists', function (): void {
    $hotFile = public_path('hot');

    file_put_contents($hotFile, 'https://127.0.0.1:5173');

    try {
        $response = app(SetSecurityHeaders::class)->handle(
            Request::create('https://my-cms.test/admin/login', 'GET'),
            fn () => response('ok', 200),
        );

        $policy = $response->headers->get('Content-Security-Policy');

        expect($policy)->toContain('script-src');
        expect($policy)->toContain('https://127.0.0.1:5173');
        expect($policy)->toContain('wss://127.0.0.1:5173');
    } finally {
        if (is_file($hotFile)) {
            unlink($hotFile);
        }
    }
});

it('redirects requests to canonical https url when enabled', function (): void {
    config()->set('app.url', 'https://my-cms.test');
    config()->set('app.enforce_canonical_url', true);

    $request = Request::create('http://my-cms.test/admin/login?from=test', 'GET');

    $response = app(RedirectToCanonicalUrl::class)->handle(
        $request,
        fn () => response('ok', 200),
    );

    expect($response->getStatusCode())->toBe(308);
    expect($response->headers->get('Location'))->toBe('https://my-cms.test/admin/login?from=test');
});