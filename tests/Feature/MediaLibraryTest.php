<?php

use App\Core\Media\Models\MediaFile;
use App\Core\Media\Models\MediaFolder;
use App\Core\Roles\Models\Permission;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AccessSeeder::class);
    Storage::fake('public');
    config()->set('media.preview_variant', 'thumbnail');
    config()->set('media.images.optimize', true);
    config()->set('media.images.keep_original', true);
    config()->set('media.images.convert_to_webp', true);
    config()->set('media.images.create_thumbnails', true);
});

it('loads media library for users with media access permission', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->where('slug', 'media.access')->pluck('id')->all());

    $this->actingAs($user)
        ->getJson('/admin/api/media')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'current_folder',
                'breadcrumbs',
                'folders',
                'files',
            ],
        ]);
});

it('creates media folders with dedicated permission', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.manage_folders'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->postJson('/admin/api/media/folders', [
            'name' => 'Hero Images',
        ])
        ->assertCreated();

    expect($response->json('data.path'))->toBe('hero-images');
    expect(MediaFolder::query()->where('path', 'hero-images')->exists())->toBeTrue();
    Storage::disk('public')->assertExists('media/hero-images');
});

it('uploads image files and stores metadata in database', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $file = UploadedFile::fake()->image('banner.webp', 1200, 630);

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => $file,
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));

    expect($mediaFile->mime_type)->toStartWith('image/');
    expect($mediaFile->width)->toBe(1200);
    expect($mediaFile->height)->toBe(630);
    expect($mediaFile->variants)->toBeArray()->not->toBeEmpty();
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['optimized']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['medium']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['large']['path']);

    expect($response->json('data.preview_url'))->not->toBeNull();
    expect($response->json('data.variants.thumbnail.url'))->not->toBeNull();
    expect($response->json('data.variants.optimized.url'))->not->toBeNull();
});

it('uploads multiple image files with custom names and returns per-item results', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files/batch', [
            'items' => [
                [
                    'name' => 'Hero Banner',
                    'file' => UploadedFile::fake()->image('first.webp', 1200, 630),
                ],
                [
                    'name' => 'Hero Banner',
                    'file' => UploadedFile::fake()->image('second.webp', 800, 600),
                ],
            ],
        ])
        ->assertOk();

    expect($response->json('data.results'))->toHaveCount(2);
    expect($response->json('data.results.0.status'))->toBe('success');
    expect($response->json('data.results.1.status'))->toBe('success');
    expect($response->json('data.results.0.data.original_name'))->toBe('Hero Banner.webp');
    expect($response->json('data.results.1.data.original_name'))->toBe('Hero Banner-1.webp');

    $filenames = MediaFile::query()->orderBy('id')->pluck('filename')->all();

    expect($filenames[0])->toBe('hero-banner.webp');
    expect($filenames[1])->toBe('hero-banner-1.webp');
});

it('uploads svg files without raster variants', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $file = UploadedFile::fake()->createWithContent(
        'vector.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));

    expect($mediaFile->mime_type)->toBe('image/svg+xml');
    expect($mediaFile->variants ?? [])->toBeArray()->toBeEmpty();
    Storage::disk('public')->assertExists($mediaFile->path);
});

it('returns per-item validation errors for invalid files in batch upload', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files/batch', [
            'items' => [
                [
                    'name' => 'Valid file',
                    'file' => UploadedFile::fake()->image('ok.jpg', 320, 240),
                ],
                [
                    'name' => '../',
                    'file' => UploadedFile::fake()->image('bad.jpg', 320, 240),
                ],
            ],
        ])
        ->assertOk();

    expect($response->json('data.results.0.status'))->toBe('success');
    expect($response->json('data.results.1.status'))->toBe('error');
    expect($response->json('data.results.1.errors.name.0'))->toContain('Укажите корректное имя файла');
});

it('deletes uploaded media files with dedicated permission', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload', 'media.delete'])->pluck('id')->all());

    $file = UploadedFile::fake()->image('gallery.jpg', 800, 600);

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => $file,
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));

    $this->actingAs($user)
        ->deleteJson("/admin/api/media/files/{$mediaFile->id}")
        ->assertNoContent();

    expect(MediaFile::query()->find($mediaFile->id))->toBeNull();
    Storage::disk('public')->assertMissing($mediaFile->path);
});

it('moves uploaded media files into another folder', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload', 'media.manage_folders'])->pluck('id')->all());

    $targetFolder = MediaFolder::query()->create([
        'name' => 'Gallery',
        'slug' => 'gallery',
        'path' => 'gallery',
    ]);

    Storage::disk('public')->makeDirectory('media/gallery');

    $uploadResponse = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => UploadedFile::fake()->image('move-me.jpg', 400, 300),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($uploadResponse->json('data.id'));
    $originalPath = $mediaFile->path;

    $this->actingAs($user)
        ->putJson("/admin/api/media/files/{$mediaFile->id}/move", [
            'folder_id' => $targetFolder->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.folder_id', $targetFolder->id)
        ->assertJsonPath('data.folder_name', 'Gallery');

    $mediaFile->refresh();

    expect($mediaFile->folder_id)->toBe($targetFolder->id);
    expect($mediaFile->path)->toStartWith('media/gallery/');
    expect($mediaFile->variants['thumbnail']['path'])->toStartWith('media/gallery/');
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('renames uploaded media files and regenerates variant paths', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => UploadedFile::fake()->image('rename-me.jpg', 640, 480),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));
    $oldPath = $mediaFile->path;
    $oldThumbPath = $mediaFile->variants['thumbnail']['path'];

    $this->actingAs($user)
        ->putJson("/admin/api/media/files/{$mediaFile->id}", [
            'original_name' => 'Cover Image',
            'title' => $mediaFile->title,
            'alt_text' => $mediaFile->alt_text,
            'caption' => $mediaFile->caption,
        ])
        ->assertOk()
        ->assertJsonPath('data.original_name', 'Cover Image.jpg')
        ->assertJsonPath('data.filename', 'cover-image.jpg');

    $mediaFile->refresh();

    expect($mediaFile->path)->toBe('media/cover-image.jpg');
    expect($mediaFile->variants['thumbnail']['path'])->toStartWith('media/thumbnail-cover-image.');
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertMissing($oldThumbPath);
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('renames media folders and updates nested file paths', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload', 'media.manage_folders'])->pluck('id')->all());

    $folder = MediaFolder::query()->create([
        'name' => 'Gallery',
        'slug' => 'gallery',
        'path' => 'gallery',
    ]);

    Storage::disk('public')->makeDirectory('media/gallery');

    $uploadResponse = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'folder_id' => $folder->id,
            'file' => UploadedFile::fake()->image('nested.jpg', 320, 240),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($uploadResponse->json('data.id'));
    $originalPath = $mediaFile->path;

    $this->actingAs($user)
        ->putJson("/admin/api/media/folders/{$folder->id}", [
            'name' => 'Portfolio',
            'parent_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.path', 'portfolio');

    $folder->refresh();
    $mediaFile->refresh();

    expect($folder->path)->toBe('portfolio');
    expect($mediaFile->path)->toStartWith('media/portfolio/');
    expect($mediaFile->variants['thumbnail']['path'])->toStartWith('media/portfolio/');
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('moves media folders under another parent and updates nested paths', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload', 'media.manage_folders'])->pluck('id')->all());

    $parent = MediaFolder::query()->create([
        'name' => 'Sections',
        'slug' => 'sections',
        'path' => 'sections',
    ]);

    $folder = MediaFolder::query()->create([
        'name' => 'Gallery',
        'slug' => 'gallery',
        'path' => 'gallery',
    ]);

    Storage::disk('public')->makeDirectory('media/sections');
    Storage::disk('public')->makeDirectory('media/gallery');

    $uploadResponse = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'folder_id' => $folder->id,
            'file' => UploadedFile::fake()->image('relocate.jpg', 320, 240),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($uploadResponse->json('data.id'));

    $this->actingAs($user)
        ->putJson("/admin/api/media/folders/{$folder->id}", [
            'name' => 'Gallery',
            'parent_id' => $parent->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.path', 'sections/gallery');

    $folder->refresh();
    $mediaFile->refresh();

    expect($folder->path)->toBe('sections/gallery');
    expect($mediaFile->path)->toStartWith('media/sections/gallery/');
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('updates media file metadata through admin api', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $uploadResponse = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => UploadedFile::fake()->image('meta.jpg', 600, 400),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($uploadResponse->json('data.id'));

    $this->actingAs($user)
        ->putJson("/admin/api/media/files/{$mediaFile->id}", [
            'title' => 'Homepage hero',
            'alt_text' => 'Main homepage hero image',
            'caption' => 'Spring campaign visual',
            'description' => 'Primary asset for homepage hero block.',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Homepage hero')
        ->assertJsonPath('data.alt_text', 'Main homepage hero image')
        ->assertJsonPath('data.caption', 'Spring campaign visual')
        ->assertJsonPath('data.description', 'Primary asset for homepage hero block.');

    $mediaFile->refresh();

    expect($mediaFile->title)->toBe('Homepage hero');
    expect($mediaFile->alt_text)->toBe('Main homepage hero image');
    expect($mediaFile->caption)->toBe('Spring campaign visual');
    expect($mediaFile->description)->toBe('Primary asset for homepage hero block.');
});

it('creates media folders with manual slug when provided', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.manage_folders'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->postJson('/admin/api/media/folders', [
            'name' => 'Hero Images',
            'slug' => 'homepage-banners',
        ])
        ->assertCreated();

    expect($response->json('data.slug'))->toBe('homepage-banners');
    expect($response->json('data.path'))->toBe('homepage-banners');
});

it('replaces uploaded media files and regenerates variants', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => UploadedFile::fake()->image('replace-me.jpg', 640, 480),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));
    $oldPath = $mediaFile->path;
    $oldMime = $mediaFile->mime_type;

    $this->actingAs($user)
        ->post("/admin/api/media/files/{$mediaFile->id}/replace", [
            'file' => UploadedFile::fake()->image('new-source.png', 1200, 800),
        ])
        ->assertOk()
        ->assertJsonPath('data.mime_type', 'image/png')
        ->assertJsonPath('data.width', 1200)
        ->assertJsonPath('data.height', 800);

    $mediaFile->refresh();

    expect($mediaFile->mime_type)->not->toBe($oldMime);
    expect($mediaFile->extension)->toBe('png');
    expect($mediaFile->path)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['optimized']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('transforms uploaded media files with crop resize and format conversion', function (): void {
    $user = User::factory()->create([
        'password' => 'StrongPass123',
    ]);

    $user->permissions()->sync(Permission::query()->whereIn('slug', ['media.access', 'media.upload'])->pluck('id')->all());

    $response = $this->actingAs($user)
        ->post('/admin/api/media/files', [
            'file' => UploadedFile::fake()->image('edit-me.jpg', 1200, 800),
        ])
        ->assertCreated();

    $mediaFile = MediaFile::query()->findOrFail($response->json('data.id'));
    $oldPath = $mediaFile->path;

    $this->actingAs($user)
        ->postJson("/admin/api/media/files/{$mediaFile->id}/transform", [
            'crop_enabled' => true,
            'crop_x' => 0.25,
            'crop_y' => 0.125,
            'crop_width' => 0.5,
            'crop_height' => 0.75,
            'resize_width' => 300,
            'resize_height' => 300,
            'maintain_aspect_ratio' => true,
            'quality' => 76,
            'format' => 'webp',
        ])
        ->assertOk()
        ->assertJsonPath('data.extension', 'webp')
        ->assertJsonPath('data.mime_type', 'image/webp')
        ->assertJsonPath('data.width', 300)
        ->assertJsonPath('data.height', 300);

    $mediaFile->refresh();

    expect($mediaFile->path)->not->toBe($oldPath);
    expect($mediaFile->extension)->toBe('webp');
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($mediaFile->path);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
});

it('regenerates variants for existing media files via artisan command', function (): void {
    $storedFile = UploadedFile::fake()->image('legacy-banner.jpg', 1400, 900);
    $storedPath = $storedFile->storeAs('media', 'legacy-banner.jpg', 'public');

    $mediaFile = MediaFile::query()->create([
        'disk' => 'public',
        'directory' => 'media',
        'filename' => 'legacy-banner.jpg',
        'original_name' => 'legacy-banner.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => Storage::disk('public')->size($storedPath),
        'width' => 1400,
        'height' => 900,
        'path' => $storedPath,
        'variants' => null,
    ]);

    $this->artisan('media:regenerate-variants')
        ->expectsOutput('Регенерировано файлов: 1')
        ->assertExitCode(0);

    $mediaFile->refresh();

    expect($mediaFile->variants)->toBeArray()->toHaveKeys(['optimized', 'thumbnail', 'medium', 'large']);
    Storage::disk('public')->assertExists($mediaFile->variants['optimized']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['thumbnail']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['medium']['path']);
    Storage::disk('public')->assertExists($mediaFile->variants['large']['path']);
});