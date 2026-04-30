<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Plugins\Blog\Http\Controllers\Site\BlogFrontendController;
use Plugins\Blog\Models\RecordType;

if (! Schema::hasTable('record_types')) {
	return;
}

$sections = RecordType::query()
	->where('status', 'active')
	->orderBy('name')
	->get(['slug'])
	->pluck('slug')
	->filter()
	->map(fn ($slug) => (string) $slug)
	->values();

if ($sections->isEmpty()) {
	$sections = collect(['blog']);
}

foreach ($sections as $slug) {

	if ($slug === '') {
		continue;
	}

	Route::prefix('/'.$slug)->group(function () use ($slug): void {
		$indexRoute = Route::get('/', [BlogFrontendController::class, 'index'])->defaults('sectionSlug', $slug);
		$categoryRoute = Route::get('/category/{slug}', [BlogFrontendController::class, 'category'])->defaults('sectionSlug', $slug);
		$tagRoute = Route::get('/tag/{slug}', [BlogFrontendController::class, 'tag'])->defaults('sectionSlug', $slug);
		$showRoute = Route::get('/{slug}', [BlogFrontendController::class, 'show'])->defaults('sectionSlug', $slug);

		if ($slug === 'blog') {
			$indexRoute->name('blog.index');
			$categoryRoute->name('blog.category');
			$tagRoute->name('blog.tag');
			$showRoute->name('blog.show');
		}
	});
}
