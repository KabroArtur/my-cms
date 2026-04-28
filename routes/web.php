<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Site\PageViewController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/admin/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/admin/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.store');
    Route::post('/admin/two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');
});

Route::middleware(['auth', 'admin.access', 'two_factor'])->prefix('/admin/api')->group(function () {
    Route::get('/me', [AdminSessionController::class, 'show']);
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media/folders', [MediaController::class, 'storeFolder']);
    Route::put('/media/folders/{folder}', [MediaController::class, 'updateFolder']);
    Route::delete('/media/folders/{folder}', [MediaController::class, 'destroyFolder']);
    Route::post('/media/files', [MediaController::class, 'storeFile']);
    Route::put('/media/files/{mediaFile}', [MediaController::class, 'updateFile']);
    Route::put('/media/files/{mediaFile}/move', [MediaController::class, 'moveFile']);
    Route::delete('/media/files/{mediaFile}', [MediaController::class, 'destroyFile']);
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages-tree', [PageController::class, 'tree']);
    Route::get('/pages-trash', [PageController::class, 'trash']);
    Route::get('/pages/{page}', [PageController::class, 'show']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::put('/pages-tree', [PageController::class, 'updateTree']);
    Route::put('/pages/{page}', [PageController::class, 'update']);
    Route::delete('/pages/{page}', [PageController::class, 'destroy']);
    Route::post('/pages/{page}/restore', [PageController::class, 'restore']);
    Route::delete('/pages/{page}/force', [PageController::class, 'forceDelete']);
});

Route::middleware(['auth', 'admin.access', 'two_factor'])->get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*');

Route::get('/', [PageViewController::class, 'home'])->name('site.home');
Route::get('/{slugPath}', [PageViewController::class, 'show'])
    ->where('slugPath', '^(?!admin(?:/|$)).*')
    ->name('site.page');