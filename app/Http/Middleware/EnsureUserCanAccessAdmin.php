<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware проверяет доступ пользователя к административной зоне.
 * Он не пропускает в админку пользователей без ролей и разрешений.
 */
class EnsureUserCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login');
        }

        if (! $user->canAccessAdmin()) {
            if ($request->expectsJson()) {
                abort(403);
            }

            abort(403);
        }

        return $next($request);
    }
}