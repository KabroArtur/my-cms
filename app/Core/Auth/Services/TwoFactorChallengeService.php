<?php

namespace App\Core\Auth\Services;

use App\Core\Auth\Models\TwoFactorChallenge;
use App\Core\Auth\Notifications\TwoFactorCodeNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Сервис управляет жизненным циклом 2FA-challenge.
 * Он создает, переотправляет и проверяет одноразовые коды.
 */
class TwoFactorChallengeService
{
    public const VERIFY_STATUS_VERIFIED = 'verified';

    public const VERIFY_STATUS_INVALID = 'invalid';

    public const VERIFY_STATUS_LOCKED = 'locked';

    public const RESEND_STATUS_SENT = 'sent';

    public const RESEND_STATUS_THROTTLED = 'throttled';

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
            'expires_at' => now()->addMinutes((int) config('auth.two_factor.challenge_expires_minutes', 10)),
            'last_sent_at' => now(),
        ]);

        RateLimiter::clear($this->verifyLimiterKey($user));

        $user->notify(new TwoFactorCodeNotification(
            code: $code,
            expiresAt: $challenge->expires_at->format('d.m.Y H:i'),
        ));

        return $challenge;
    }

    /**
     * Сервис проверяет код и закрывает challenge при успехе.
     */
    public function verify(User $user, string $code): array
    {
        $challenge = $this->activeChallenge($user);

        if ($challenge === null) {
            return [
                'status' => self::VERIFY_STATUS_INVALID,
            ];
        }

        $maxAttempts = (int) config('auth.two_factor.verify.max_attempts', 5);
        $verifyKey = $this->verifyLimiterKey($user);

        if (RateLimiter::tooManyAttempts($verifyKey, $maxAttempts)) {
            return [
                'status' => self::VERIFY_STATUS_LOCKED,
                'retry_after' => RateLimiter::availableIn($verifyKey),
            ];
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            RateLimiter::hit(
                $verifyKey,
                (int) config('auth.two_factor.verify.decay_seconds', 300),
            );

            return [
                'status' => self::VERIFY_STATUS_INVALID,
            ];
        }

        $challenge->forceFill([
            'completed_at' => now(),
        ])->save();

        RateLimiter::clear($verifyKey);

        return [
            'status' => self::VERIFY_STATUS_VERIFIED,
        ];
    }

    /**
     * Сервис повторно отправляет код с cooldown между отправками.
     */
    public function resend(User $user): array
    {
        $challenge = $this->activeChallenge($user);
        $cooldownSeconds = (int) config('auth.two_factor.resend.cooldown_seconds', 60);

        if ($challenge !== null && $challenge->last_sent_at !== null) {
            $availableAt = $challenge->last_sent_at->copy()->addSeconds($cooldownSeconds);

            if ($availableAt->isFuture()) {
                return [
                    'status' => self::RESEND_STATUS_THROTTLED,
                    'retry_after' => now()->diffInSeconds($availableAt),
                ];
            }
        }

        return [
            'status' => self::RESEND_STATUS_SENT,
            'challenge' => $this->issue($user),
        ];
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

    protected function verifyLimiterKey(User $user): string
    {
        return sprintf('two-factor:verify:%s', $user->getKey());
    }
}