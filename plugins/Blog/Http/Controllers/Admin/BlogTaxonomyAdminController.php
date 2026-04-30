<?php

namespace Plugins\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\BlogTag;

class BlogTaxonomyAdminController extends Controller
{
    public function categories(Request $request): View
    {
        $this->guard($request, 'blog.categories.manage');

        return view('blog-plugin::admin.categories.index', [
            'categories' => BlogCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->guard($request, 'blog.categories.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_categories,slug'],
            'description' => ['nullable', 'string'],
        ]);

        BlogCategory::query()->create([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
            'description' => $payload['description'] ?? null,
        ]);

        return back()->with('status', 'Категория создана.');
    }

    public function updateCategory(Request $request, BlogCategory $category): RedirectResponse
    {
        $this->guard($request, 'blog.categories.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_categories,slug,'.$category->id],
            'description' => ['nullable', 'string'],
        ]);

        $category->update([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
            'description' => $payload['description'] ?? null,
        ]);

        return back()->with('status', 'Категория обновлена.');
    }

    public function destroyCategory(Request $request, BlogCategory $category): RedirectResponse
    {
        $this->guard($request, 'blog.categories.manage');
        $category->delete();

        return back()->with('status', 'Категория удалена.');
    }

    public function tags(Request $request): View
    {
        $this->guard($request, 'blog.tags.manage');

        return view('blog-plugin::admin.tags.index', [
            'tags' => BlogTag::query()->orderBy('name')->get(),
        ]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $this->guard($request, 'blog.tags.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_tags,slug'],
        ]);

        BlogTag::query()->create([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
        ]);

        return back()->with('status', 'Тег создан.');
    }

    public function updateTag(Request $request, BlogTag $tag): RedirectResponse
    {
        $this->guard($request, 'blog.tags.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:blog_tags,slug,'.$tag->id],
        ]);

        $tag->update([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
        ]);

        return back()->with('status', 'Тег обновлен.');
    }

    public function destroyTag(Request $request, BlogTag $tag): RedirectResponse
    {
        $this->guard($request, 'blog.tags.manage');
        $tag->delete();

        return back()->with('status', 'Тег удален.');
    }

    private function guard(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
