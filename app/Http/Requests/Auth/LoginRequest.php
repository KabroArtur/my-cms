<?php

namespace App\Http\Requests\Auth;

use App\Core\Security\Services\SecurityAuditLogger;
use App\Core\Security\Services\SecurityRuntimeSettings;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Запрос валидирует вход по логину и паролю.
 * Он держит базовую защиту от перебора через rate limit.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Запрос описывает обязательные поля для входа.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Запрос выполняет попытку входа и выбрасывает ошибку при неудаче.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotBanned();
        $this->ensureIsNotRateLimited();

        $settings = app(SecurityRuntimeSettings::class)->all();
        $decaySeconds = max(30, (int) ($settings['security_login_decay_seconds'] ?? 120));
        $maxAttempts = max(3, (int) ($settings['security_login_max_attempts'] ?? 5));
        $banSeconds = max(60, (int) ($settings['security_login_ban_seconds'] ?? 1800));

        $credentials = [
            'username' => (string) $this->string('login'),
            'password' => (string) $this->string('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), $decaySeconds);

            $failedAttempts = (int) Cache::increment($this->failedAttemptsKey());
            Cache::put($this->failedAttemptsKey(), $failedAttempts, now()->addSeconds($decaySeconds));

            if ($failedAttempts >= $maxAttempts) {
                Cache::put($this->banKey(), now()->addSeconds($banSeconds)->timestamp, now()->addSeconds($banSeconds));
                Cache::forget($this->failedAttemptsKey());
            }

            app(SecurityAuditLogger::class)->log('auth.login_failed', null, [
                'login' => (string) $this->string('login'),
                'attempts' => $failedAttempts,
            ]);

            throw ValidationException::withMessages([
                'login' => 'Неверный логин или пароль.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Cache::forget($this->failedAttemptsKey());
        Cache::forget($this->banKey());
    }

    /**
     * Запрос прерывает попытку при превышении лимита входа.
     */
    public function ensureIsNotRateLimited(): void
    {
        $settings = app(SecurityRuntimeSettings::class)->all();
        $maxAttempts = max(3, (int) ($settings['security_login_max_attempts'] ?? 5));

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        app(SecurityAuditLogger::class)->log('auth.login_lockout', null, [
            'login' => (string) $this->string('login'),
            'retry_after' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'login' => "Слишком много попыток. Повторите через {$seconds} сек.",
        ]);
    }

    protected function ensureIsNotBanned(): void
    {
        $until = Cache::get($this->banKey());

        if (! is_numeric($until)) {
            return;
        }

        $seconds = max(1, ((int) $until) - now()->timestamp);

        if ($seconds <= 0) {
            Cache::forget($this->banKey());

            return;
        }

        throw ValidationException::withMessages([
            'login' => "Вход временно заблокирован из-за подозрительной активности. Повторите через {$seconds} сек.",
        ]);
    }

    /**
     * Запрос строит ключ ограничения по логину и IP.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }

    protected function failedAttemptsKey(): string
    {
        return 'cms:auth:failed:'.$this->throttleKey();
    }

    protected function banKey(): string
    {
        return 'cms:auth:ban:'.$this->throttleKey();
    }
}