<?php

namespace App\Http\Controllers\Auth;

use App\Core\Auth\Services\TwoFactorChallengeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Контроллер обслуживает вход и выход из административной зоны.
 * Он держит простой session-based сценарий авторизации.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Контроллер показывает простую форму входа.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/admin/pages');
        }

        return view('auth.login');
    }

    /**
     * Контроллер аутентифицирует пользователя по логину и паролю.
     */
    public function store(LoginRequest $request, TwoFactorChallengeService $twoFactor): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user === null || ! $user->canAccessAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Доступ в административную систему запрещен.',
            ]);
        }

        $request->session()->forget('auth.two_factor_confirmed_user_id');

        if ($user->requiresTwoFactorChallenge()) {
            $twoFactor->issue($user);

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended('/admin/pages');
    }

    /**
     * Контроллер завершает пользовательскую сессию.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget('auth.two_factor_confirmed_user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}