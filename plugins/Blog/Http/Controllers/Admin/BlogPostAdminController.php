<?php

namespace Plugins\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\BlogPost;
use Plugins\Blog\Models\BlogTag;

class BlogPostAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard($request, 'blog.access');

        $posts = BlogPost::query()->with(['category', 'tags', 'author'])->latest()->paginate(20);
        $categories = BlogCategory::query()->orderBy('name')->get();
        $tags = BlogTag::query()->orderBy('name')->get();

        return view('blog-plugin::admin.posts.index', [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request, 'blog.posts.manage');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_posts,slug'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $post = BlogPost::query()->create([
            'title' => $payload['title'],
            'slug' => $payload['slug'] ?? Str::slug($payload['title']),
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'author_id' => $request->user()?->id,
            'is_published' => (bool) ($payload['is_published'] ?? false),
            'published_at' => ($payload['is_published'] ?? false) ? now() : null,
        ]);

        $post->tags()->sync($payload['tags'] ?? []);

        return back()->with('status', 'Запись создана.');
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $this->guard($request, 'blog.posts.manage');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_posts,slug,'.$post->id],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $isPublished = (bool) ($payload['is_published'] ?? false);

        $post->update([
            'title' => $payload['title'],
            'slug' => $payload['slug'] ?? Str::slug($payload['title']),
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($post->published_at ?? now()) : null,
        ]);

        $post->tags()->sync($payload['tags'] ?? []);

        return back()->with('status', 'Запись обновлена.');
    }

    public function destroy(Request $request, BlogPost $post): RedirectResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $post->delete();

        return back()->with('status', 'Запись удалена.');
    }

    private function guard(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
