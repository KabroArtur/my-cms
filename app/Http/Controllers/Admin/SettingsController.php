<?php

namespace App\Http\Controllers\Admin;

use App\Core\Security\Services\AdminPathManager;
use App\Core\Security\Services\SecurityRuntimeSettings;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Support\Services\CmsCacheService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSiteSettingsRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class SettingsController extends Controller
{
    protected const GENERAL_SETTING_KEYS = [
        'site_name',
        'favicon_media_id',
        'date_format',
        'time_format',
        'home_page_id',
        'site_theme',
    ];

    protected const APPEARANCE_SETTING_KEYS = [
        'site_featured_media_variant',
        'media_default_insert_variant',
        'admin_theme_mode',
        'admin_light_palette',
        'admin_dark_palette',
        'cms_palette',
        'theme_assets_minify_css',
        'theme_assets_minify_js',
        'theme_assets_combine_css',
        'theme_assets_combine_js',
        'theme_assets_separate_css',
        'theme_assets_separate_js',
        'theme_assets_defer_scripts',
        'theme_assets_use_hash',
    ];

    protected const CACHE_SETTING_KEYS = [
        'cache_enabled',
        'cache_data_enabled',
        'cache_response_enabled',
        'cache_response_ttl',
    ];

    protected const SECURITY_SETTING_KEYS = [
        'admin_entry_path',
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

    public function __construct(
        protected SettingsManager $settings,
        protected CmsCacheService $cache,
        protected AdminPathManager $adminPath,
        protected SecurityRuntimeSettings $security,
    )
    {
    }

    public function show(): JsonResponse
    {
        $permissions = $this->resolveSettingsPermissions(request()->user());
        $canManageGeneral = $permissions['settings.general.manage'];
        $canManageAppearance = $permissions['settings.appearance.manage'];
        $canManageCache = $permissions['settings.cache.manage'];
        $canManageSecurity = $permissions['settings.security.manage'];

        $payload = $this->settings->adminPayload();

        if (! $canManageGeneral) {
            foreach (self::GENERAL_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if (! $canManageAppearance) {
            foreach (self::APPEARANCE_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if (! $canManageCache) {
            foreach (self::CACHE_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if ($canManageSecurity) {
            $payload['settings']['admin_entry_path'] = $this->adminPath->currentPath();
        } else {
            foreach (self::SECURITY_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        $adminPathPayload = $canManageSecurity
            ? [
                'current' => $this->adminPath->currentPath(),
                'login_url' => url($this->adminPath->loginPath()),
            ]
            : null;

        return response()->json([
            'data' => array_merge($payload, [
                'cache' => $this->cache->stats(),
                'admin_path' => $adminPathPayload,
                'permissions' => [
                    'settings.general.manage' => $canManageGeneral,
                    'settings.appearance.manage' => $canManageAppearance,
                    'settings.cache.manage' => $canManageCache,
                    'settings.security.manage' => $canManageSecurity,
                ],
            ]),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): JsonResponse
    {
        $permissions = $this->resolveSettingsPermissions($request->user());
        $canManageGeneral = $permissions['settings.general.manage'];
        $canManageAppearance = $permissions['settings.appearance.manage'];
        $canManageCache = $permissions['settings.cache.manage'];
        $canManageSecurity = $permissions['settings.security.manage'];

        if (! $canManageGeneral && $request->hasAny(self::GENERAL_SETTING_KEYS)) {
            abort(403);
        }

        if (! $canManageAppearance && $request->hasAny(self::APPEARANCE_SETTING_KEYS)) {
            abort(403);
        }

        if (! $canManageCache && $request->hasAny(self::CACHE_SETTING_KEYS)) {
            abort(403);
        }

        if (! $canManageSecurity && $request->hasAny(self::SECURITY_SETTING_KEYS)) {
            abort(403);
        }

        $validated = $request->validated();
        $nextAdminPath = $validated['admin_entry_path'] ?? null;

        unset($validated['admin_entry_path']);

        $this->settings->update($validated);
        $this->security->clearCache();

        $adminPathResult = [
            'changed' => false,
            'new_url_once' => null,
        ];

        if (is_string($nextAdminPath) && $nextAdminPath !== '') {
            $adminPathResult = $this->adminPath->updatePath($nextAdminPath);

            if (($adminPathResult['changed'] ?? false) === true) {
                try {
                    Artisan::call('route:clear');
                } catch (\Throwable) {
                    // No-op: обновление пути должно оставаться рабочим даже без доступа к Artisan.
                }
            }
        }

        $payload = $this->settings->adminPayload();

        if (! $canManageGeneral) {
            foreach (self::GENERAL_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if (! $canManageAppearance) {
            foreach (self::APPEARANCE_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if (! $canManageCache) {
            foreach (self::CACHE_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        if ($canManageSecurity) {
            $payload['settings']['admin_entry_path'] = $this->adminPath->currentPath();
        } else {
            foreach (self::SECURITY_SETTING_KEYS as $key) {
                unset($payload['settings'][$key]);
            }
        }

        $adminPathPayload = $canManageSecurity
            ? [
                'current' => $this->adminPath->currentPath(),
                'login_url' => url($this->adminPath->loginPath()),
                'new_url_once' => $adminPathResult['new_url_once'] ?? null,
            ]
            : null;

        return response()->json([
            'data' => array_merge($payload, [
                'cache' => $this->cache->stats(),
                'admin_path' => $adminPathPayload,
                'permissions' => [
                    'settings.general.manage' => $canManageGeneral,
                    'settings.appearance.manage' => $canManageAppearance,
                    'settings.cache.manage' => $canManageCache,
                    'settings.security.manage' => $canManageSecurity,
                ],
            ]),
        ]);
    }

    public function clearCache(Request $request): JsonResponse
    {
        $permissions = $this->resolveSettingsPermissions($request->user());
        abort_unless($permissions['settings.cache.manage'] ?? false, 403);

        $versions = $this->cache->clearStoreAndInvalidateAll(
            userId: $request->user()?->id,
            reason: 'manual-admin-clear',
        );

        return response()->json([
            'message' => 'Кэш сайта и CMS очищен.',
            'data' => [
                'versions' => $versions,
                'cache' => $this->cache->stats(),
            ],
        ]);
    }

    /**
     * @return array<string, bool>
     */
    protected function resolveSettingsPermissions(?User $user): array
    {
        $hasSettingsAccess = $user?->hasPermission('settings.access') ?? false;
        $general = $user?->hasPermission('settings.general.manage') ?? false;
        $appearance = $user?->hasPermission('settings.appearance.manage') ?? false;
        $cache = $user?->hasPermission('settings.cache.manage') ?? false;
        $security = $user?->hasPermission('settings.security.manage') ?? false;

        // Backward compatibility for roles that still have only settings.access.
        if ($hasSettingsAccess && ! $general && ! $appearance && ! $cache) {
            $general = true;
            $appearance = true;
            $cache = true;
        }

        return [
            'settings.general.manage' => $general,
            'settings.appearance.manage' => $appearance,
            'settings.cache.manage' => $cache,
            'settings.security.manage' => $security,
        ];
    }
}