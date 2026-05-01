<?php

use App\Core\Security\Services\AdminPathManager;
use App\Core\Modules\Services\PluginManager;
use App\Http\Controllers\Admin\AdminSessionController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\AdditionalFieldGroupController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Site\PageViewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$adminPathManager = app(AdminPathManager::class);
$adminPath = $adminPathManager->currentPath();
$legacyAdminPath = $adminPathManager->legacyPath();

if ($legacyAdminPath !== null && $legacyAdminPath !== $adminPath) {
    Route::any('/'.$legacyAdminPath.'/{any?}', function (Request $request, ?string $any = null) use ($adminPathManager) {
        $legacy = $adminPathManager->legacyPath();

        if ($legacy === null) {
            abort(404);
        }

        $target = '/'.$adminPathManager->currentPath();

        if ($any !== null && $any !== '') {
            $target .= '/'.ltrim($any, '/');
        }

        $query = $request->getQueryString();

        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect($target, 307);
    })->where('any', '.*');
}

Route::middleware('guest')->group(function () use ($adminPath) {
    Route::get('/'.$adminPath.'/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/'.$adminPath.'/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth-login')
        ->name('login.store');
});

Route::middleware('auth')->post('/'.$adminPath.'/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () use ($adminPath) {
    Route::get('/'.$adminPath.'/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/'.$adminPath.'/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.store');
    Route::post('/'.$adminPath.'/two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');
});

Route::middleware(['auth', 'admin.access', 'two_factor', 'throttle:admin-api'])->prefix('/'.$adminPath.'/api')->group(function () {
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
    Route::post('/media/files/batch', [MediaController::class, 'storeFiles']);
    Route::put('/media/files/{mediaFile}', [MediaController::class, 'updateFile']);
    Route::put('/media/files/{mediaFile}/move', [MediaController::class, 'moveFile']);
    Route::delete('/media/files/{mediaFile}', [MediaController::class, 'destroyFile']);
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/cache/clear', [SettingsController::class, 'clearCache']);
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
    Route::get('/field-groups', [AdditionalFieldGroupController::class, 'index']);
    Route::get('/field-groups/applicable', [AdditionalFieldGroupController::class, 'applicable']);
    Route::post('/field-groups', [AdditionalFieldGroupController::class, 'store']);
    Route::put('/field-groups/{group}', [AdditionalFieldGroupController::class, 'update']);
    Route::delete('/field-groups/{group}', [AdditionalFieldGroupController::class, 'destroy']);
    Route::get('/plugins', [PluginController::class, 'index']);
    Route::post('/plugins/{slug}/install', [PluginController::class, 'install']);
    Route::post('/plugins/{slug}/enable', [PluginController::class, 'enable']);
    Route::post('/plugins/{slug}/disable', [PluginController::class, 'disable']);
    Route::delete('/plugins/{slug}', [PluginController::class, 'destroy']);
});

$pluginManager = app(PluginManager::class);

foreach ($pluginManager->enabledRouteFiles('admin') as $routeFile) {
    require $routeFile;
}

Route::middleware(['auth', 'admin.access', 'two_factor'])->get('/'.$adminPath.'/{any?}', function () {
    return view('admin');
})->where('any', '.*');

Route::get('/', [PageViewController::class, 'home'])->name('site.home');

foreach ($pluginManager->enabledRouteFiles('web') as $routeFile) {
    require $routeFile;
}

Route::get('/{slugPath}', [PageViewController::class, 'show'])
    ->where('slugPath', '.*')
    ->name('site.page');