<?php

use App\Http\Middleware\EnsureUserCanAccessAdmin;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.access' => EnsureUserCanAccessAdmin::class,
            'two_factor' => EnsureTwoFactorIsConfirmed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
