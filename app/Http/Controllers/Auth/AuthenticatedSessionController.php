<?php

namespace App\Http\Controllers\Auth;

use App\Core\Security\Services\AdminPathManager;
use App\Core\Auth\Services\TwoFactorChallengeService;
use App\Core\Security\Services\SecurityAuditLogger;
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
            return redirect(app(AdminPathManager::class)->pagesPath());
        }

        return view('auth.login');
    }

    /**
     * Контроллер аутентифицирует пользователя по логину и паролю.
     */
    public function store(LoginRequest $request, TwoFactorChallengeService $twoFactor, SecurityAuditLogger $audit): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        if ($user === null || ! $user->canAccessAdmin()) {
            $audit->log('auth.admin_access_denied', $user, [
                'login' => (string) $request->string('login'),
            ]);

            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Доступ в административную систему запрещен.',
            ]);
        }

        $request->session()->forget('auth.two_factor_confirmed_user_id');

        if ($user->requiresTwoFactorChallenge()) {
            $audit->log('auth.password_verified', $user, [
                'two_factor_required' => true,
            ]);

            $twoFactor->issue($user);

            return redirect()->route('two-factor.challenge');
        }

        $audit->log('auth.login_succeeded', $user, [
            'two_factor_required' => false,
        ]);

        return redirect()->intended(app(AdminPathManager::class)->pagesPath());
    }

    /**
     * Контроллер завершает пользовательскую сессию.
     */
    public function destroy(Request $request, SecurityAuditLogger $audit): RedirectResponse
    {
        $audit->log('auth.logout', $request->user());

        Auth::guard('web')->logout();

        $request->session()->forget('auth.two_factor_confirmed_user_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}