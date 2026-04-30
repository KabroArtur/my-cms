<?php

namespace Plugins\Blog\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Plugins\Blog\Models\Record;
use Plugins\Blog\Models\RecordCategory;
use Plugins\Blog\Models\RecordTag;
use Plugins\Blog\Models\RecordType;

class BlogFrontendController extends Controller
{
    public function index(string $sectionSlug): View
    {
        $section = $this->section($sectionSlug);

        $posts = Record::query()
            ->where('record_type_id', $section->id)
            ->where('status', 'published')
            ->with(['category', 'tags'])
            ->latest('published_at')
            ->paginate(10);

        return view('blog-plugin::site.blog.index', [
            'section' => $section,
            'posts' => $posts,
        ]);
    }

    public function show(string $sectionSlug, string $slug): View
    {
        $section = $this->section($sectionSlug);

        $post = Record::query()
            ->where('record_type_id', $section->id)
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with(['category', 'tags'])
            ->firstOrFail();

        return view('blog-plugin::site.blog.show', [
            'section' => $section,
            'post' => $post,
        ]);
    }

    public function category(string $sectionSlug, string $slug): View
    {
        $section = $this->section($sectionSlug);
        $category = RecordCategory::query()
            ->where('record_type_id', $section->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Record::query()
            ->where('record_type_id', $section->id)
            ->where('category_id', $category->id)
            ->where('status', 'published')
            ->latest('published_at')
            ->paginate(10);

        return view('blog-plugin::site.taxonomy.category', [
            'section' => $section,
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    public function tag(string $sectionSlug, string $slug): View
    {
        $section = $this->section($sectionSlug);
        $tag = RecordTag::query()
            ->where('record_type_id', $section->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = $tag->records()
            ->where('record_type_id', $section->id)
            ->where('status', 'published')
            ->latest('published_at')
            ->paginate(10);

        return view('blog-plugin::site.taxonomy.tag', [
            'section' => $section,
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }

    protected function section(string $slug): RecordType
    {
        return RecordType::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();
    }
}
