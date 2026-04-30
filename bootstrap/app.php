<?php

use App\Core\Security\Services\AdminPathManager;
use App\Http\Middleware\EmergencyModeMiddleware;
use App\Http\Middleware\EnsureUserCanAccessAdmin;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\ProtectAgainstDdos;
use App\Http\Middleware\RedirectToCanonicalUrl;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
        )));

        $middleware->trustProxies(
            at: $trustedProxies === ['*'] ? '*' : $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        $middleware->append(SetSecurityHeaders::class);
        $middleware->append(RedirectToCanonicalUrl::class);
        $middleware->append(ProtectAgainstDdos::class);
        $middleware->append(EmergencyModeMiddleware::class);

        $middleware->alias([
            'admin.access' => EnsureUserCanAccessAdmin::class,
            'two_factor' => EnsureTwoFactorIsConfirmed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, $request) {
            if ($request->expectsJson() || app(AdminPathManager::class)->isAdminRequest($request)) {
                return null;
            }

            return response(
                view()->file(base_path('themes/default/404.blade.php'))->render(),
                404,
            );
        });
    })->create();
