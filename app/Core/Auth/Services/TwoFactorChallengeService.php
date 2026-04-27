<?php

namespace App\Core\Auth\Services;

use App\Core\Auth\Models\TwoFactorChallenge;
use App\Core\Auth\Notifications\TwoFactorCodeNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Сервис управляет жизненным циклом 2FA-challenge.
 * Он создает, переотправляет и проверяет одноразовые коды.
 */
class TwoFactorChallengeService
{
    /**
     * Сервис создает новый challenge и отправляет код пользователю.
     */
    public function issue(User $user): TwoFactorChallenge
    {
        TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->delete();

        $code = (string) random_int(100000, 999999);
        $challenge = TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'channel' => $user->two_factor_channel ?? 'email',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
        ]);

        $user->notify(new TwoFactorCodeNotification(
            code: $code,
            expiresAt: $challenge->expires_at->format('d.m.Y H:i'),
        ));

        return $challenge;
    }

    /**
     * Сервис проверяет код и закрывает challenge при успехе.
     */
    public function verify(User $user, string $code): bool
    {
        $challenge = $this->activeChallenge($user);

        if ($challenge === null) {
            return false;
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->forceFill([
            'completed_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Сервис возвращает текущий активный challenge пользователя.
     */
    public function activeChallenge(User $user): ?TwoFactorChallenge
    {
        return TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->active()
            ->latest('id')
            ->first();
    }
}