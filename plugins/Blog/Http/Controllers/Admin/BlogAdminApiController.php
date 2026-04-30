<?php

namespace Plugins\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Plugins\Blog\Models\Record;
use Plugins\Blog\Models\RecordCategory;
use Plugins\Blog\Models\RecordTag;
use Plugins\Blog\Models\RecordType;

class BlogAdminApiController extends Controller
{
    public function sections(Request $request): JsonResponse
    {
        $this->guard($request, 'blog.access');

        $sections = RecordType::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'items' => $sections,
        ]);
    }

    public function storeSection(Request $request): JsonResponse
    {
        $this->guard($request, 'blog.settings.manage');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:record_types,slug'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'has_categories' => ['nullable', 'boolean'],
            'has_tags' => ['nullable', 'boolean'],
            'has_seo' => ['nullable', 'boolean'],
            'has_featured_image' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);

        $section = RecordType::query()->create([
            'name' => $payload['title'],
            'slug' => $payload['slug'] ?? Str::slug($payload['title']),
            'has_categories' => (bool) ($payload['has_categories'] ?? true),
            'has_tags' => (bool) ($payload['has_tags'] ?? true),
            'has_seo' => (bool) ($payload['has_seo'] ?? true),
            'has_featured_image' => (bool) ($payload['has_featured_image'] ?? true),
            'status' => (string) ($payload['status'] ?? 'active'),
        ]);

        return response()->json([
            'item' => $section,
        ], 201);
    }

    public function updateSection(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.settings.manage');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('record_types', 'slug')->ignore($section->id)],
            'has_categories' => ['nullable', 'boolean'],
            'has_tags' => ['nullable', 'boolean'],
            'has_seo' => ['nullable', 'boolean'],
            'has_featured_image' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
        ]);

        $section->update([
            'name' => $payload['title'],
            'slug' => $payload['slug'] ?? Str::slug($payload['title']),
            'has_categories' => (bool) ($payload['has_categories'] ?? true),
            'has_tags' => (bool) ($payload['has_tags'] ?? true),
            'has_seo' => (bool) ($payload['has_seo'] ?? true),
            'has_featured_image' => (bool) ($payload['has_featured_image'] ?? true),
            'status' => (string) ($payload['status'] ?? 'active'),
        ]);

        return response()->json([
            'item' => $section,
        ]);
    }

    public function destroySection(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.settings.manage');

        $section->delete();

        return response()->json(['ok' => true]);
    }

    public function posts(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.access');

        $posts = Record::query()
            ->where('record_type_id', $section->id)
            ->with(['category:id,name,slug', 'tags:id,name,slug', 'author:id,name,username'])
            ->latest()
            ->get();

        return response()->json([
            'section' => $section,
            'items' => $posts,
        ]);
    }

    public function storePost(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists('record_categories', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('record_tags', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $isPublished = ($payload['status'] ?? null) === 'published' || (bool) ($payload['is_published'] ?? false);
        $slug = trim((string) ($payload['slug'] ?? ''));

        if ($slug === '') {
            $slug = Str::slug($payload['title']);
        }

        $post = Record::query()->create([
            'record_type_id' => $section->id,
            'title' => $payload['title'],
            'slug' => $this->resolveUniqueRecordSlug($section->id, $slug),
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'author_id' => $request->user()?->id,
            'status' => $isPublished ? 'published' : 'draft',
            'published_at' => $isPublished ? now() : null,
        ]);

        $post->tags()->sync($payload['tag_ids'] ?? []);

        return response()->json([
            'item' => $post->load(['category:id,name,slug', 'tags:id,name,slug', 'author:id,name,username']),
        ], 201);
    }

    public function updatePost(Request $request, RecordType $section, Record $post): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $this->ensureRecordBelongsToSection($post, $section);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists('record_categories', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('record_tags', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $isPublished = ($payload['status'] ?? null) === 'published' || (bool) ($payload['is_published'] ?? false);
        $slug = trim((string) ($payload['slug'] ?? ''));

        if ($slug === '') {
            $slug = Str::slug($payload['title']);
        }

        $post->update([
            'title' => $payload['title'],
            'slug' => $this->resolveUniqueRecordSlug($section->id, $slug, $post->id),
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'status' => $isPublished ? 'published' : 'draft',
            'published_at' => $isPublished ? ($post->published_at ?? now()) : null,
        ]);

        $post->tags()->sync($payload['tag_ids'] ?? []);

        return response()->json([
            'item' => $post->load(['category:id,name,slug', 'tags:id,name,slug', 'author:id,name,username']),
        ]);
    }

    public function destroyPost(Request $request, RecordType $section, Record $post): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $this->ensureRecordBelongsToSection($post, $section);

        $post->delete();

        return response()->json(['ok' => true]);
    }

    public function publishPost(Request $request, RecordType $section, Record $post): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $this->ensureRecordBelongsToSection($post, $section);

        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
        ]);

        return response()->json(['item' => $post]);
    }

    public function unpublishPost(Request $request, RecordType $section, Record $post): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $this->ensureRecordBelongsToSection($post, $section);

        $post->update([
            'status' => 'draft',
            'published_at' => null,
        ]);

        return response()->json(['item' => $post]);
    }

    public function duplicatePost(Request $request, RecordType $section, Record $post): JsonResponse
    {
        $this->guard($request, 'blog.posts.manage');
        $this->ensureRecordBelongsToSection($post, $section);

        $newSlug = $this->resolveUniqueRecordSlug($section->id, Str::slug($post->slug.'-copy'));

        $copy = Record::query()->create([
            'record_type_id' => $section->id,
            'author_id' => $request->user()?->id,
            'category_id' => $post->category_id,
            'title' => $post->title.' (копия)',
            'slug' => $newSlug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $copy->tags()->sync($post->tags()->pluck('record_tags.id')->all());

        return response()->json([
            'item' => $copy->load(['category:id,name,slug', 'tags:id,name,slug', 'author:id,name,username']),
        ], 201);
    }

    public function categories(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.access');

        return response()->json([
            'section' => $section,
            'items' => RecordCategory::query()
                ->where('record_type_id', $section->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeCategory(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.categories.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('record_categories', 'slug')->where(fn ($query) => $query->where('record_type_id', $section->id)),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('record_categories', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
        ]);

        $category = RecordCategory::query()->create([
            'record_type_id' => $section->id,
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
            'parent_id' => $payload['parent_id'] ?? null,
        ]);

        return response()->json(['item' => $category], 201);
    }

    public function updateCategory(Request $request, RecordType $section, RecordCategory $category): JsonResponse
    {
        $this->guard($request, 'blog.categories.manage');
        $this->ensureCategoryBelongsToSection($category, $section);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('record_categories', 'slug')
                    ->where(fn ($query) => $query->where('record_type_id', $section->id))
                    ->ignore($category->id),
            ],
            'parent_id' => ['nullable', 'integer', Rule::exists('record_categories', 'id')->where(fn ($query) => $query->where('record_type_id', $section->id))],
        ]);

        $category->update([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
            'parent_id' => $payload['parent_id'] ?? null,
        ]);

        return response()->json(['item' => $category]);
    }

    public function destroyCategory(Request $request, RecordType $section, RecordCategory $category): JsonResponse
    {
        $this->guard($request, 'blog.categories.manage');
        $this->ensureCategoryBelongsToSection($category, $section);

        $category->delete();

        return response()->json(['ok' => true]);
    }

    public function tags(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.access');

        return response()->json([
            'section' => $section,
            'items' => RecordTag::query()
                ->where('record_type_id', $section->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeTag(Request $request, RecordType $section): JsonResponse
    {
        $this->guard($request, 'blog.tags.manage');

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('record_tags', 'slug')->where(fn ($query) => $query->where('record_type_id', $section->id)),
            ],
        ]);

        $tag = RecordTag::query()->create([
            'record_type_id' => $section->id,
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
        ]);

        return response()->json(['item' => $tag], 201);
    }

    public function updateTag(Request $request, RecordType $section, RecordTag $tag): JsonResponse
    {
        $this->guard($request, 'blog.tags.manage');
        $this->ensureTagBelongsToSection($tag, $section);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('record_tags', 'slug')
                    ->where(fn ($query) => $query->where('record_type_id', $section->id))
                    ->ignore($tag->id),
            ],
        ]);

        $tag->update([
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? Str::slug($payload['name']),
        ]);

        return response()->json(['item' => $tag]);
    }

    public function destroyTag(Request $request, RecordType $section, RecordTag $tag): JsonResponse
    {
        $this->guard($request, 'blog.tags.manage');
        $this->ensureTagBelongsToSection($tag, $section);

        $tag->delete();

        return response()->json(['ok' => true]);
    }

    protected function resolveUniqueRecordSlug(int $recordTypeId, string $slugCandidate, ?int $ignoreRecordId = null): string
    {
        $baseSlug = Str::slug($slugCandidate);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'record';
        $slug = $baseSlug;
        $index = 1;

        while (Record::query()
            ->where('record_type_id', $recordTypeId)
            ->where('slug', $slug)
            ->when($ignoreRecordId !== null, fn ($query) => $query->where('id', '!=', $ignoreRecordId))
            ->exists()) {
            $index++;
            $slug = $baseSlug.'-'.$index;
        }

        return $slug;
    }

    protected function ensureRecordBelongsToSection(Record $record, RecordType $section): void
    {
        abort_unless((int) $record->record_type_id === (int) $section->id, 404);
    }

    protected function ensureCategoryBelongsToSection(RecordCategory $category, RecordType $section): void
    {
        abort_unless((int) $category->record_type_id === (int) $section->id, 404);
    }

    protected function ensureTagBelongsToSection(RecordTag $tag, RecordType $section): void
    {
        abort_unless((int) $tag->record_type_id === (int) $section->id, 404);
    }

    private function guard(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
