<?php

namespace App\Core\Security\Services;

use App\Core\Settings\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class AdminPathManager
{
    protected const SETTING_KEY = 'admin_path_ciphertext';

    protected const LEGACY_CACHE_KEY = 'cms:admin_path:legacy';

    protected ?bool $hasSettingsTable = null;

    protected ?string $resolvedCurrentPath = null;

    public function currentPath(): string
    {
        if ($this->resolvedCurrentPath !== null) {
            return $this->resolvedCurrentPath;
        }

        $default = $this->sanitizePath((string) config('cms.admin_path', 'admin'));

        if (! $this->settingsTableExists()) {
            $this->resolvedCurrentPath = $default;

            return $this->resolvedCurrentPath;
        }

        $setting = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        if (! is_string($setting) || $setting === '') {
            $this->resolvedCurrentPath = $default;

            return $this->resolvedCurrentPath;
        }

        $decoded = json_decode($setting, true);

        if (! is_string($decoded) || $decoded === '') {
            $this->resolvedCurrentPath = $default;

            return $this->resolvedCurrentPath;
        }

        try {
            $decrypted = (string) Crypt::decryptString($decoded);
        } catch (\Throwable) {
            $this->resolvedCurrentPath = $default;

            return $this->resolvedCurrentPath;
        }

        $candidate = $this->sanitizePath($decrypted);

        $this->resolvedCurrentPath = $this->isAllowedPath($candidate) ? $candidate : $default;

        return $this->resolvedCurrentPath;
    }

    public function basePath(): string
    {
        return '/'.$this->currentPath();
    }

    public function apiBasePath(): string
    {
        return $this->basePath().'/api';
    }

    public function loginPath(): string
    {
        return $this->basePath().'/login';
    }

    public function pagesPath(): string
    {
        return $this->basePath().'/pages';
    }

    public function makePath(string $tail = ''): string
    {
        $tail = trim($tail);

        if ($tail === '') {
            return $this->basePath();
        }

        return $this->basePath().'/'.ltrim($tail, '/');
    }

    public function isAllowedPath(string $path): bool
    {
        $path = $this->sanitizePath($path);

        if ($path === '' || strlen($path) < 3 || strlen($path) > 40) {
            return false;
        }

        if (! preg_match('/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?$/', $path)) {
            return false;
        }

        $restricted = array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item, '/')),
            (array) config('cms.restricted_admin_paths', []),
        );

        return ! in_array($path, $restricted, true);
    }

    public function updatePath(string $newPath): array
    {
        $newPath = $this->sanitizePath($newPath);

        if (! $this->isAllowedPath($newPath) || ! $this->settingsTableExists()) {
            return [
                'changed' => false,
                'current_path' => $this->currentPath(),
                'previous_path' => null,
                'new_url_once' => null,
            ];
        }

        $currentPath = $this->currentPath();

        if ($newPath === $currentPath) {
            return [
                'changed' => false,
                'current_path' => $currentPath,
                'previous_path' => null,
                'new_url_once' => null,
            ];
        }

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode(Crypt::encryptString($newPath), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );

        $graceSeconds = max(0, (int) config('cms.admin_path_grace_seconds', 300));

        if ($graceSeconds > 0) {
            Cache::put(self::LEGACY_CACHE_KEY, [
                'path' => $currentPath,
                'expires_at' => now()->addSeconds($graceSeconds)->timestamp,
            ], now()->addSeconds($graceSeconds));
        }

        $this->resolvedCurrentPath = $newPath;
        config(['cms.admin_path' => $newPath]);

        return [
            'changed' => true,
            'current_path' => $newPath,
            'previous_path' => $currentPath,
            'new_url_once' => url('/'.$newPath),
        ];
    }

    public function legacyPath(): ?string
    {
        $entry = Cache::get(self::LEGACY_CACHE_KEY);

        if (! is_array($entry)) {
            return null;
        }

        $legacy = $this->sanitizePath((string) ($entry['path'] ?? ''));
        $expiresAt = (int) ($entry['expires_at'] ?? 0);

        if ($legacy === '' || $expiresAt <= now()->timestamp) {
            Cache::forget(self::LEGACY_CACHE_KEY);

            return null;
        }

        if ($legacy === $this->currentPath()) {
            return null;
        }

        return $legacy;
    }

    public function isAdminRequest(Request $request): bool
    {
        $path = trim((string) $request->path(), '/');

        if ($path === '') {
            return false;
        }

        $firstSegment = strtolower((string) strtok($path, '/'));

        if ($firstSegment === $this->currentPath()) {
            return true;
        }

        $legacy = $this->legacyPath();

        return $legacy !== null && $firstSegment === $legacy;
    }

    protected function sanitizePath(string $path): string
    {
        return strtolower(trim(trim($path), '/'));
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
