<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware пропускает в админку только после прохождения второго фактора.
 * Он отделяет факт логина по паролю от полного подтверждения сессии.
 */
class EnsureTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->requiresTwoFactorChallenge()) {
            return $next($request);
        }

        $confirmedUserId = $request->session()->get('auth.two_factor_confirmed_user_id');

        if ((int) $confirmedUserId === $user->id) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, 'Two-factor challenge required.');
        }

        return redirect()->route('two-factor.challenge');
    }
}