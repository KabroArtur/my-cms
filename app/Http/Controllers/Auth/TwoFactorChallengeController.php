<?php

namespace App\Http\Controllers\Auth;

use App\Core\Auth\Services\TwoFactorChallengeService;
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
            return redirect('/admin/pages');
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
    public function store(TwoFactorChallengeRequest $request, TwoFactorChallengeService $service): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $service->verify($user, (string) $request->string('code'))) {
            return back()
                ->withErrors(['code' => 'Неверный или просроченный код.'])
                ->withInput();
        }

        $request->session()->put('auth.two_factor_confirmed_user_id', $user->id);

        return redirect()->intended('/admin/pages');
    }

    /**
     * Контроллер повторно отправляет код второго фактора.
     */
    public function resend(Request $request, TwoFactorChallengeService $service): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $service->issue($user);

        return back()->with('status', 'Новый код отправлен.');
    }
}