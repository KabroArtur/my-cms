<?php

use App\Core\Security\Services\AdminPathManager;
use Illuminate\Support\Facades\Route;
use Plugins\Blog\Http\Controllers\Admin\BlogAdminApiController;

$adminPath = app(AdminPathManager::class)->currentPath();

Route::middleware(['auth', 'admin.access', 'two_factor'])
    ->prefix('/'.$adminPath.'/api/blog')
    ->group(function (): void {
        Route::get('/sections', [BlogAdminApiController::class, 'sections'])->name('blog.admin.api.sections.index');
        Route::post('/sections', [BlogAdminApiController::class, 'storeSection'])->name('blog.admin.api.sections.store');
        Route::put('/sections/{section}', [BlogAdminApiController::class, 'updateSection'])->name('blog.admin.api.sections.update');
        Route::delete('/sections/{section}', [BlogAdminApiController::class, 'destroySection'])->name('blog.admin.api.sections.destroy');

        Route::get('/sections/{section}/records', [BlogAdminApiController::class, 'posts'])->name('blog.admin.api.records.index');
        Route::post('/sections/{section}/records', [BlogAdminApiController::class, 'storePost'])->name('blog.admin.api.records.store');
        Route::put('/sections/{section}/records/{post}', [BlogAdminApiController::class, 'updatePost'])->name('blog.admin.api.records.update');
        Route::delete('/sections/{section}/records/{post}', [BlogAdminApiController::class, 'destroyPost'])->name('blog.admin.api.records.destroy');
        Route::post('/sections/{section}/records/{post}/publish', [BlogAdminApiController::class, 'publishPost'])->name('blog.admin.api.records.publish');
        Route::post('/sections/{section}/records/{post}/unpublish', [BlogAdminApiController::class, 'unpublishPost'])->name('blog.admin.api.records.unpublish');
        Route::post('/sections/{section}/records/{post}/duplicate', [BlogAdminApiController::class, 'duplicatePost'])->name('blog.admin.api.records.duplicate');

        Route::get('/sections/{section}/categories', [BlogAdminApiController::class, 'categories'])->name('blog.admin.api.categories.index');
        Route::post('/sections/{section}/categories', [BlogAdminApiController::class, 'storeCategory'])->name('blog.admin.api.categories.store');
        Route::put('/sections/{section}/categories/{category}', [BlogAdminApiController::class, 'updateCategory'])->name('blog.admin.api.categories.update');
        Route::delete('/sections/{section}/categories/{category}', [BlogAdminApiController::class, 'destroyCategory'])->name('blog.admin.api.categories.destroy');

        Route::get('/sections/{section}/tags', [BlogAdminApiController::class, 'tags'])->name('blog.admin.api.tags.index');
        Route::post('/sections/{section}/tags', [BlogAdminApiController::class, 'storeTag'])->name('blog.admin.api.tags.store');
        Route::put('/sections/{section}/tags/{tag}', [BlogAdminApiController::class, 'updateTag'])->name('blog.admin.api.tags.update');
        Route::delete('/sections/{section}/tags/{tag}', [BlogAdminApiController::class, 'destroyTag'])->name('blog.admin.api.tags.destroy');
    });
