<?php

namespace App\Http\Controllers\Auth;

use App\Core\Auth\Services\TwoFactorChallengeService;
use App\Core\Security\Services\AdminPathManager;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Контроллер обслуживает второй шаг входа через одноразовый код.
 * Он работает после логина по паролю и до входа в административную зону.
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * Контроллер показывает форму ввода одноразового кода.
     */
    public function create(Request $request, TwoFactorChallengeService $service): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->requiresTwoFactorChallenge()) {
            return redirect(app(AdminPathManager::class)->pagesPath());
        }

        if ($service->activeChallenge($user) === null) {
            $service->issue($user);
        }

        return view('auth.two-factor-challenge', [
            'user' => $user,
        ]);
    }

    /**
     * Контроллер проверяет код и завершает 2FA-подтверждение.
     */
    public function store(TwoFactorChallengeRequest $request, TwoFactorChallengeService $service, SecurityAuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $verification = $service->verify($user, (string) $request->string('code'));

        if (($verification['status'] ?? null) === TwoFactorChallengeService::VERIFY_STATUS_LOCKED) {
            $audit->log('auth.two_factor_locked', $user, [
                'retry_after' => (int) ($verification['retry_after'] ?? 0),
            ]);

            return back()
                ->withErrors([
                    'code' => 'Слишком много неверных попыток. Попробуйте через '.max(1, (int) ($verification['retry_after'] ?? 0)).' сек.',
                ]);
        }

        if (($verification['status'] ?? null) !== TwoFactorChallengeService::VERIFY_STATUS_VERIFIED) {
            $audit->log('auth.two_factor_failed', $user);

            return back()
                ->withErrors(['code' => 'Неверный или просроченный код.'])
                ->withInput();
        }

        $request->session()->put('auth.two_factor_confirmed_user_id', $user->id);

        $audit->log('auth.two_factor_verified', $user);

        return redirect()->intended(app(AdminPathManager::class)->pagesPath());
    }

    /**
     * Контроллер повторно отправляет код второго фактора.
     */
    public function resend(Request $request, TwoFactorChallengeService $service, SecurityAuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $resend = $service->resend($user);

        if (($resend['status'] ?? null) === TwoFactorChallengeService::RESEND_STATUS_THROTTLED) {
            $audit->log('auth.two_factor_resend_throttled', $user, [
                'retry_after' => (int) ($resend['retry_after'] ?? 0),
            ]);

            return back()->withErrors([
                'code' => 'Повторная отправка будет доступна через '.max(1, (int) ($resend['retry_after'] ?? 0)).' сек.',
            ]);
        }

        $audit->log('auth.two_factor_resent', $user);

        return back()->with('status', 'Новый код отправлен.');
    }
}