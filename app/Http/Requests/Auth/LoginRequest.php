<?php

namespace App\Http\Requests\Auth;

use App\Core\Security\Services\SecurityAuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
        $this->ensureIsNotRateLimited();

        $credentials = [
            'username' => (string) $this->string('login'),
            'password' => (string) $this->string('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            app(SecurityAuditLogger::class)->log('auth.login_failed', null, [
                'login' => (string) $this->string('login'),
            ]);

            throw ValidationException::withMessages([
                'login' => 'Неверный логин или пароль.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Запрос прерывает попытку при превышении лимита входа.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
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

    /**
     * Запрос строит ключ ограничения по логину и IP.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}