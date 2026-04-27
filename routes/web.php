<?php

use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Site\PageViewController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.store');
    Route::post('/two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');
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
    Route::get('/pages', [PageController::class, 'index']);
    Route::get('/pages-trash', [PageController::class, 'trash']);
    Route::get('/pages/{page}', [PageController::class, 'show']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::put('/pages/{page}', [PageController::class, 'update']);
    Route::delete('/pages/{page}', [PageController::class, 'destroy']);
    Route::post('/pages/{page}/restore', [PageController::class, 'restore']);
    Route::delete('/pages/{page}/force', [PageController::class, 'forceDelete']);
});

Route::middleware(['auth', 'admin.access', 'two_factor'])->get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*');

Route::get('/', [PageViewController::class, 'home'])->name('site.home');
Route::get('/{slug}', [PageViewController::class, 'show'])
    ->where('slug', '^(?!admin|login|logout|two-factor).+$')
    ->name('site.page');