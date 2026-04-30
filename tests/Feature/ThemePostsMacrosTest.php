<?php

use App\Core\Themes\Services\ThemeRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns published posts from theme tables through posts macro', function (): void {
    $categoryId = DB::table('theme_post_categories')->insertGetId([
        'name' => 'Тестовая',
        'slug' => 'test',
        'description' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('theme_posts')->insert([
        [
            'category_id' => $categoryId,
            'title' => 'Новая запись',
            'slug' => 'new-post',
            'excerpt' => 'excerpt',
            'content' => 'content',
            'is_published' => true,
            'published_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'category_id' => $categoryId,
            'title' => 'Черновик',
            'slug' => 'draft-post',
            'excerpt' => 'draft',
            'content' => 'draft',
            'is_published' => false,
            'published_at' => now()->subMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $runtime = app(ThemeRuntime::class);

    $posts = $runtime->posts(['limit' => 10]);
    $categories = $runtime->categories();

    expect($posts)->toHaveCount(1)
        ->and($posts[0]->slug)->toBe('new-post')
        ->and($posts[0]->url)->toBe('/posts/new-post')
        ->and($posts[0]->category->slug)->toBe('test')
        ->and($categories)->toHaveCount(1)
        ->and($categories[0]->slug)->toBe('test');
});
