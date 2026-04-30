<?php

namespace App\Core\Security\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityRuntimeSettings
{
    protected const CACHE_KEY = 'cms:security:runtime-settings:v1';

    protected const CACHE_TTL_SECONDS = 30;

    protected ?bool $hasSettingsTable = null;

    protected ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $defaults = [
            'security_rate_limit_enabled' => (bool) config('cms.security.rate_limit_enabled', true),
            'security_rate_limit_per_minute' => max(30, (int) config('cms.security.rate_limit_per_minute', 180)),
            'security_rate_limit_burst_per_10s' => max(10, (int) config('cms.security.rate_limit_burst_per_10s', 45)),
            'security_ip_ban_seconds' => max(60, (int) config('cms.security.ip_ban_seconds', 900)),
            'security_login_max_attempts' => max(3, (int) config('cms.security.login_max_attempts', 5)),
            'security_login_decay_seconds' => max(30, (int) config('cms.security.login_decay_seconds', 120)),
            'security_login_ban_seconds' => max(60, (int) config('cms.security.login_ban_seconds', 1800)),
            'security_emergency_mode' => (bool) config('cms.security.emergency_mode', false),
            'security_emergency_message' => (string) config('cms.security.emergency_message', 'Сервис временно недоступен.'),
        ];

        if (! $this->settingsTableExists()) {
            $this->resolved = $defaults;

            return $this->resolved;
        }

        $stored = Cache::remember(self::CACHE_KEY, now()->addSeconds(self::CACHE_TTL_SECONDS), function (): array {
            $keys = [
                'security_rate_limit_enabled',
                'security_rate_limit_per_minute',
                'security_rate_limit_burst_per_10s',
                'security_ip_ban_seconds',
                'security_login_max_attempts',
                'security_login_decay_seconds',
                'security_login_ban_seconds',
                'security_emergency_mode',
                'security_emergency_message',
            ];

            return DB::table('settings')
                ->whereIn('key', $keys)
                ->pluck('value', 'key')
                ->map(fn (mixed $value): mixed => is_string($value) ? json_decode($value, true) : null)
                ->all();
        });

        $this->resolved = [
            'security_rate_limit_enabled' => $this->resolveBool($stored['security_rate_limit_enabled'] ?? $defaults['security_rate_limit_enabled'], $defaults['security_rate_limit_enabled']),
            'security_rate_limit_per_minute' => $this->resolveInt($stored['security_rate_limit_per_minute'] ?? $defaults['security_rate_limit_per_minute'], 30, 2000, $defaults['security_rate_limit_per_minute']),
            'security_rate_limit_burst_per_10s' => $this->resolveInt($stored['security_rate_limit_burst_per_10s'] ?? $defaults['security_rate_limit_burst_per_10s'], 10, 1000, $defaults['security_rate_limit_burst_per_10s']),
            'security_ip_ban_seconds' => $this->resolveInt($stored['security_ip_ban_seconds'] ?? $defaults['security_ip_ban_seconds'], 60, 86400, $defaults['security_ip_ban_seconds']),
            'security_login_max_attempts' => $this->resolveInt($stored['security_login_max_attempts'] ?? $defaults['security_login_max_attempts'], 3, 20, $defaults['security_login_max_attempts']),
            'security_login_decay_seconds' => $this->resolveInt($stored['security_login_decay_seconds'] ?? $defaults['security_login_decay_seconds'], 30, 3600, $defaults['security_login_decay_seconds']),
            'security_login_ban_seconds' => $this->resolveInt($stored['security_login_ban_seconds'] ?? $defaults['security_login_ban_seconds'], 60, 86400, $defaults['security_login_ban_seconds']),
            'security_emergency_mode' => $this->resolveBool($stored['security_emergency_mode'] ?? $defaults['security_emergency_mode'], $defaults['security_emergency_mode']),
            'security_emergency_message' => $this->resolveString($stored['security_emergency_message'] ?? $defaults['security_emergency_message'], $defaults['security_emergency_message']),
        ];

        return $this->resolved;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->resolved = null;
    }

    protected function resolveBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function resolveInt(mixed $value, int $min, int $max, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    protected function resolveString(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    protected function settingsTableExists(): bool
    {
        if ($this->hasSettingsTable !== null) {
            return $this->hasSettingsTable;
        }

        $this->hasSettingsTable = Schema::hasTable('settings');

        return $this->hasSettingsTable;
    }
}
