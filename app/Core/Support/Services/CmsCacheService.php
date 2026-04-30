<?php

namespace App\Core\Support\Services;

use App\Core\Support\Models\CacheInvalidationLog;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CmsCacheService
{
    protected const SITE_VERSION_KEY = 'cms:cache:version:site';

    protected const CMS_VERSION_KEY = 'cms:cache:version:cms';

    protected const DEFAULT_SITE_TTL = 300;

    protected const DEFAULT_CMS_TTL = 300;

    protected ?int $siteVersion = null;

    protected ?int $cmsVersion = null;

    protected ?bool $hasLogTable = null;

    protected ?bool $hasSettingsTable = null;

    protected ?bool $dataCacheEnabled = null;

    protected ?bool $responseCacheEnabled = null;

    protected ?int $responseCacheTtl = null;

    public function rememberSite(string $key, Closure $resolver, int $ttlSeconds = self::DEFAULT_SITE_TTL): mixed
    {
        return $this->rememberScoped('site', $key, $resolver, $ttlSeconds);
    }

    public function rememberCms(string $key, Closure $resolver, int $ttlSeconds = self::DEFAULT_CMS_TTL): mixed
    {
        return $this->rememberScoped('cms', $key, $resolver, $ttlSeconds);
    }

    public function invalidateSite(?int $userId = null, ?string $reason = null): int
    {
        return $this->bumpVersion('site', $userId, $reason ?? 'site-content-changed');
    }

    public function invalidateCms(?int $userId = null, ?string $reason = null): int
    {
        return $this->bumpVersion('cms', $userId, $reason ?? 'cms-data-changed');
    }

    public function invalidateAll(?int $userId = null, ?string $reason = null): array
    {
        $this->dataCacheEnabled = null;
        $this->responseCacheEnabled = null;
        $this->responseCacheTtl = null;

        return [
            'site_version' => $this->invalidateSite($userId, $reason ?? 'full-cache-clear'),
            'cms_version' => $this->invalidateCms($userId, $reason ?? 'full-cache-clear'),
        ];
    }

    public function clearStoreAndInvalidateAll(?int $userId = null, ?string $reason = null): array
    {
        Cache::flush();
        $this->siteVersion = null;
        $this->cmsVersion = null;

        return $this->invalidateAll($userId, $reason ?? 'manual-store-flush');
    }

    public function stats(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'data_enabled' => $this->isDataCacheEnabled(),
            'response_enabled' => $this->isResponseCacheEnabled(),
            'response_ttl' => $this->responseCacheTtl(),
            'site_version' => $this->currentVersion('site'),
            'cms_version' => $this->currentVersion('cms'),
            'last_cleared_at' => $this->lastClearedAt(),
        ];
    }

    protected function rememberScoped(string $scope, string $key, Closure $resolver, int $ttlSeconds): mixed
    {
        if (! $this->isDataCacheEnabled()) {
            return $resolver();
        }

        $cacheKey = sprintf(
            'cms:cache:%s:v%s:%s',
            $scope,
            $this->currentVersion($scope),
            sha1($key),
        );

        if ($ttlSeconds <= 0) {
            return Cache::rememberForever($cacheKey, $resolver);
        }

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), $resolver);
    }

    public function isEnabled(): bool
    {
        return $this->isDataCacheEnabled() && $this->isResponseCacheEnabled();
    }

    public function isDataCacheEnabled(): bool
    {
        if ($this->dataCacheEnabled !== null) {
            return $this->dataCacheEnabled;
        }

        $legacyDefault = (bool) config('settings.defaults.cache_enabled', true);
        $default = (bool) config('settings.defaults.cache_data_enabled', $legacyDefault);

        if (! $this->settingsTableExists()) {
            $this->dataCacheEnabled = $default;

            return $this->dataCacheEnabled;
        }

        $decoded = $this->readBooleanSetting('cache_data_enabled');
        if ($decoded === null) {
            $decoded = $this->readBooleanSetting('cache_enabled');
        }

        $resolved = filter_var($decoded ?? $default, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        $this->dataCacheEnabled = $resolved ?? $default;

        return $this->dataCacheEnabled;
    }

    public function isResponseCacheEnabled(): bool
    {
        if ($this->responseCacheEnabled !== null) {
            return $this->responseCacheEnabled;
        }

        $legacyDefault = (bool) config('settings.defaults.cache_enabled', true);
        $default = (bool) config('settings.defaults.cache_response_enabled', $legacyDefault);

        if (! $this->settingsTableExists()) {
            $this->responseCacheEnabled = $default;

            return $this->responseCacheEnabled;
        }

        $decoded = $this->readBooleanSetting('cache_response_enabled');
        if ($decoded === null) {
            $decoded = $this->readBooleanSetting('cache_enabled');
        }

        $resolved = filter_var($decoded ?? $default, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        $this->responseCacheEnabled = $resolved ?? $default;

        return $this->responseCacheEnabled;
    }

    public function responseCacheTtl(): int
    {
        if ($this->responseCacheTtl !== null) {
            return $this->responseCacheTtl;
        }

        $default = max(0, (int) config('settings.defaults.cache_response_ttl', config('cms.response_cache_ttl', 0)));

        if (! $this->settingsTableExists()) {
            $this->responseCacheTtl = $default;

            return $this->responseCacheTtl;
        }

        $decoded = $this->readSetting('cache_response_ttl');
        $this->responseCacheTtl = is_numeric($decoded) ? max(0, (int) $decoded) : $default;

        return $this->responseCacheTtl;
    }

    protected function readBooleanSetting(string $key): bool|null
    {
        $value = $this->readSetting($key);

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    protected function readSetting(string $key): mixed
    {
        $raw = DB::table('settings')->where('key', $key)->value('value');

        return is_string($raw) ? json_decode($raw, true) : null;
    }

    protected function currentVersion(string $scope): int
    {
        if ($scope === 'site' && $this->siteVersion !== null) {
            return $this->siteVersion;
        }

        if ($scope === 'cms' && $this->cmsVersion !== null) {
            return $this->cmsVersion;
        }

        $key = $scope === 'site' ? self::SITE_VERSION_KEY : self::CMS_VERSION_KEY;
        $cached = Cache::get($key);

        if (! is_int($cached) || $cached < 1) {
            Cache::forever($key, 1);
            $cached = 1;
        }

        if ($scope === 'site') {
            $this->siteVersion = $cached;
        } else {
            $this->cmsVersion = $cached;
        }

        return $cached;
    }

    protected function bumpVersion(string $scope, ?int $userId, ?string $reason): int
    {
        $key = $scope === 'site' ? self::SITE_VERSION_KEY : self::CMS_VERSION_KEY;
        $current = $this->currentVersion($scope);
        $next = max(1, $current + 1);

        Cache::forever($key, $next);

        if ($scope === 'site') {
            $this->siteVersion = $next;
        } else {
            $this->cmsVersion = $next;
        }

        $this->writeLog($scope, $reason, $userId);

        return $next;
    }

    protected function writeLog(string $scope, ?string $reason, ?int $userId): void
    {
        if (! $this->logTableExists()) {
            return;
        }

        CacheInvalidationLog::query()->create([
            'scope' => $scope,
            'reason' => $reason,
            'triggered_by' => $userId,
        ]);
    }

    protected function lastClearedAt(): ?string
    {
        if (! $this->logTableExists()) {
            return null;
        }

        $entry = CacheInvalidationLog::query()
            ->latest('id')
            ->first();

        return $entry?->created_at?->toAtomString();
    }

    protected function logTableExists(): bool
    {
        if ($this->hasLogTable !== null) {
            return $this->hasLogTable;
        }

        $this->hasLogTable = Schema::hasTable('cache_invalidation_logs');

        return $this->hasLogTable;
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
