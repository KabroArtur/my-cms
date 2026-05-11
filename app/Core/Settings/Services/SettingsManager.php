<?php

namespace App\Core\Settings\Services;

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Models\Setting;
use App\Core\Support\Services\CmsCacheService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SettingsManager
{
    public function __construct(
        protected CmsCacheService $cache,
    ) {
    }

    protected ?array $allCache = null;

    protected ?array $publicPayloadCache = null;

    protected ?array $themeDefinitionsCache = null;

    protected array $pageTemplateOptionsCache = [];

    public function all(): array
    {
        if ($this->allCache !== null) {
            return $this->allCache;
        }

        $stored = Setting::query()
            ->get(['key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->key => json_decode((string) $setting->value, true),
            ])
            ->all();

        $settings = array_merge(config('settings.defaults', []), $stored);
        $settings['site_theme'] = $this->resolveTheme((string) ($settings['site_theme'] ?? 'default'));
        $settings['admin_theme_mode'] = $this->resolveThemeMode((string) ($settings['admin_theme_mode'] ?? 'dark'));
        $settings['admin_light_palette'] = $this->resolveLightPalette((string) ($settings['admin_light_palette'] ?? 'slate'));
        $settings['admin_dark_palette'] = $this->resolveDarkPalette((string) ($settings['admin_dark_palette'] ?? 'midnight'));

        $legacyPalette = (string) ($settings['cms_palette'] ?? '');

        if ($legacyPalette !== '') {
            if (array_key_exists($legacyPalette, $this->lightPaletteOptions())) {
                $settings['admin_theme_mode'] = 'light';
                $settings['admin_light_palette'] = $legacyPalette;
            }

            if (array_key_exists($legacyPalette, $this->darkPaletteOptions())) {
                $settings['admin_theme_mode'] = 'dark';
                $settings['admin_dark_palette'] = $legacyPalette;
            }
        }

        $settings['cms_palette'] = $settings['admin_theme_mode'] === 'dark'
            ? $settings['admin_dark_palette']
            : $settings['admin_light_palette'];
        $settings['site_featured_media_variant'] = $this->resolveMediaVariant((string) ($settings['site_featured_media_variant'] ?? 'original'));
        $settings['media_default_insert_variant'] = $this->resolveMediaVariant((string) ($settings['media_default_insert_variant'] ?? 'original'));
        $settings['media_image_optimize'] = $this->resolveBool($settings['media_image_optimize'] ?? config('settings.defaults.media_image_optimize', config('media.images.optimize', true)), config('settings.defaults.media_image_optimize', config('media.images.optimize', true)));
        $settings['media_image_max_width'] = $this->resolveBoundedInt($settings['media_image_max_width'] ?? config('settings.defaults.media_image_max_width', config('media.images.max_width', 1920)), 320, 12000, (int) config('settings.defaults.media_image_max_width', config('media.images.max_width', 1920)));
        $settings['media_image_max_height'] = $this->resolveBoundedInt($settings['media_image_max_height'] ?? config('settings.defaults.media_image_max_height', config('media.images.max_height', 1920)), 320, 12000, (int) config('settings.defaults.media_image_max_height', config('media.images.max_height', 1920)));
        $settings['media_image_jpg_quality'] = $this->resolveBoundedInt($settings['media_image_jpg_quality'] ?? config('settings.defaults.media_image_jpg_quality', config('media.images.jpg_quality', 82)), 30, 100, (int) config('settings.defaults.media_image_jpg_quality', config('media.images.jpg_quality', 82)));
        $settings['media_image_webp_quality'] = $this->resolveBoundedInt($settings['media_image_webp_quality'] ?? config('settings.defaults.media_image_webp_quality', config('media.images.webp_quality', 80)), 30, 100, (int) config('settings.defaults.media_image_webp_quality', config('media.images.webp_quality', 80)));
        $settings['media_image_convert_to_webp'] = $this->resolveBool($settings['media_image_convert_to_webp'] ?? config('settings.defaults.media_image_convert_to_webp', config('media.images.convert_to_webp', true)), config('settings.defaults.media_image_convert_to_webp', config('media.images.convert_to_webp', true)));
        $settings['media_image_keep_original'] = $this->resolveBool($settings['media_image_keep_original'] ?? config('settings.defaults.media_image_keep_original', config('media.images.keep_original', true)), config('settings.defaults.media_image_keep_original', config('media.images.keep_original', true)));
        $settings['media_image_create_thumbnails'] = $this->resolveBool($settings['media_image_create_thumbnails'] ?? config('settings.defaults.media_image_create_thumbnails', config('media.images.create_thumbnails', true)), config('settings.defaults.media_image_create_thumbnails', config('media.images.create_thumbnails', true)));
        $settings['theme_assets_minify_css'] = $this->resolveBool($settings['theme_assets_minify_css'] ?? true, true);
        $settings['theme_assets_minify_js'] = $this->resolveBool($settings['theme_assets_minify_js'] ?? true, true);
        $settings['theme_assets_obfuscate_js'] = $this->resolveBool($settings['theme_assets_obfuscate_js'] ?? false, false);
        $settings['theme_assets_obfuscation_preset'] = $this->resolveThemeAssetObfuscationPreset((string) ($settings['theme_assets_obfuscation_preset'] ?? 'balanced'));
        $settings['theme_assets_combine_css'] = $this->resolveBool($settings['theme_assets_combine_css'] ?? true, true);
        $settings['theme_assets_combine_js'] = $this->resolveBool($settings['theme_assets_combine_js'] ?? true, true);
        $settings['theme_assets_separate_css'] = $this->resolveBool($settings['theme_assets_separate_css'] ?? false, false);
        $settings['theme_assets_separate_js'] = $this->resolveBool($settings['theme_assets_separate_js'] ?? false, false);
        $settings['theme_assets_defer_scripts'] = $this->resolveBool($settings['theme_assets_defer_scripts'] ?? true, true);
        $settings['theme_assets_use_hash'] = $this->resolveBool($settings['theme_assets_use_hash'] ?? true, true);
        $legacyCacheEnabled = $this->resolveBool($settings['cache_enabled'] ?? true, true);
        $settings['cache_data_enabled'] = $this->resolveBool($settings['cache_data_enabled'] ?? $legacyCacheEnabled, $legacyCacheEnabled);
        $settings['cache_response_enabled'] = $this->resolveBool($settings['cache_response_enabled'] ?? $legacyCacheEnabled, $legacyCacheEnabled);
        $settings['cache_enabled'] = $settings['cache_data_enabled'] && $settings['cache_response_enabled'];
        $settings['cache_response_ttl'] = $this->resolveCacheTtl($settings['cache_response_ttl'] ?? 0);
        $settings['security_rate_limit_enabled'] = $this->resolveBool($settings['security_rate_limit_enabled'] ?? true, true);
        $settings['security_rate_limit_per_minute'] = $this->resolveBoundedInt($settings['security_rate_limit_per_minute'] ?? 180, 30, 2000, 180);
        $settings['security_rate_limit_burst_per_10s'] = $this->resolveBoundedInt($settings['security_rate_limit_burst_per_10s'] ?? 45, 10, 1000, 45);
        $settings['security_ip_ban_seconds'] = $this->resolveBoundedInt($settings['security_ip_ban_seconds'] ?? 900, 60, 86400, 900);
        $settings['security_login_max_attempts'] = $this->resolveBoundedInt($settings['security_login_max_attempts'] ?? 5, 3, 20, 5);
        $settings['security_login_decay_seconds'] = $this->resolveBoundedInt($settings['security_login_decay_seconds'] ?? 120, 30, 3600, 120);
        $settings['security_login_ban_seconds'] = $this->resolveBoundedInt($settings['security_login_ban_seconds'] ?? 1800, 60, 86400, 1800);
        $settings['security_emergency_mode'] = $this->resolveBool($settings['security_emergency_mode'] ?? false, false);
        $settings['security_emergency_message'] = $this->nullableString($settings['security_emergency_message'] ?? null) ?? (string) config('settings.defaults.security_emergency_message', 'Сайт временно недоступен.');

        $homePageId = Arr::get($settings, 'home_page_id');
        $settings['home_page_id'] = is_numeric($homePageId) ? (int) $homePageId : null;

        $faviconId = Arr::get($settings, 'favicon_media_id');
        $settings['favicon_media_id'] = is_numeric($faviconId) ? (int) $faviconId : null;

        $this->allCache = $settings;

        return $this->allCache;
    }

    public function update(array $attributes): array
    {
        $current = $this->all();
        $legacyCacheEnabled = array_key_exists('cache_enabled', $attributes)
            ? $this->resolveBool($attributes['cache_enabled'], $current['cache_enabled'] ?? true)
            : null;

        $settings = array_merge($current, [
            'site_name' => $this->nullableString($attributes['site_name'] ?? null) ?? (string) config('settings.defaults.site_name', 'My CMS'),
            'favicon_media_id' => $this->nullableInt($attributes['favicon_media_id'] ?? null),
            'date_format' => $this->resolveDateFormat((string) ($attributes['date_format'] ?? config('settings.defaults.date_format', 'd.m.Y'))),
            'time_format' => $this->resolveTimeFormat((string) ($attributes['time_format'] ?? config('settings.defaults.time_format', 'H:i'))),
            'home_page_id' => $this->nullableInt($attributes['home_page_id'] ?? null),
            'site_theme' => $this->resolveTheme((string) ($attributes['site_theme'] ?? 'default')),
            'site_featured_media_variant' => $this->resolveMediaVariant((string) ($attributes['site_featured_media_variant'] ?? 'original')),
            'media_default_insert_variant' => $this->resolveMediaVariant((string) ($attributes['media_default_insert_variant'] ?? 'original')),
            'media_image_optimize' => $this->resolveBool($attributes['media_image_optimize'] ?? $current['media_image_optimize'] ?? config('settings.defaults.media_image_optimize', config('media.images.optimize', true)), config('settings.defaults.media_image_optimize', config('media.images.optimize', true))),
            'media_image_max_width' => $this->resolveBoundedInt($attributes['media_image_max_width'] ?? $current['media_image_max_width'] ?? config('settings.defaults.media_image_max_width', config('media.images.max_width', 1920)), 320, 12000, (int) config('settings.defaults.media_image_max_width', config('media.images.max_width', 1920))),
            'media_image_max_height' => $this->resolveBoundedInt($attributes['media_image_max_height'] ?? $current['media_image_max_height'] ?? config('settings.defaults.media_image_max_height', config('media.images.max_height', 1920)), 320, 12000, (int) config('settings.defaults.media_image_max_height', config('media.images.max_height', 1920))),
            'media_image_jpg_quality' => $this->resolveBoundedInt($attributes['media_image_jpg_quality'] ?? $current['media_image_jpg_quality'] ?? config('settings.defaults.media_image_jpg_quality', config('media.images.jpg_quality', 82)), 30, 100, (int) config('settings.defaults.media_image_jpg_quality', config('media.images.jpg_quality', 82))),
            'media_image_webp_quality' => $this->resolveBoundedInt($attributes['media_image_webp_quality'] ?? $current['media_image_webp_quality'] ?? config('settings.defaults.media_image_webp_quality', config('media.images.webp_quality', 80)), 30, 100, (int) config('settings.defaults.media_image_webp_quality', config('media.images.webp_quality', 80))),
            'media_image_convert_to_webp' => $this->resolveBool($attributes['media_image_convert_to_webp'] ?? $current['media_image_convert_to_webp'] ?? config('settings.defaults.media_image_convert_to_webp', config('media.images.convert_to_webp', true)), config('settings.defaults.media_image_convert_to_webp', config('media.images.convert_to_webp', true))),
            'media_image_keep_original' => $this->resolveBool($attributes['media_image_keep_original'] ?? $current['media_image_keep_original'] ?? config('settings.defaults.media_image_keep_original', config('media.images.keep_original', true)), config('settings.defaults.media_image_keep_original', config('media.images.keep_original', true))),
            'media_image_create_thumbnails' => $this->resolveBool($attributes['media_image_create_thumbnails'] ?? $current['media_image_create_thumbnails'] ?? config('settings.defaults.media_image_create_thumbnails', config('media.images.create_thumbnails', true)), config('settings.defaults.media_image_create_thumbnails', config('media.images.create_thumbnails', true))),
            'admin_theme_mode' => $this->resolveThemeMode((string) ($attributes['admin_theme_mode'] ?? $current['admin_theme_mode'] ?? 'dark')),
            'admin_light_palette' => $this->resolveLightPalette((string) ($attributes['admin_light_palette'] ?? $current['admin_light_palette'] ?? 'slate')),
            'admin_dark_palette' => $this->resolveDarkPalette((string) ($attributes['admin_dark_palette'] ?? $current['admin_dark_palette'] ?? 'midnight')),
            'theme_assets_minify_css' => $this->resolveBool($attributes['theme_assets_minify_css'] ?? $current['theme_assets_minify_css'] ?? true, true),
            'theme_assets_minify_js' => $this->resolveBool($attributes['theme_assets_minify_js'] ?? $current['theme_assets_minify_js'] ?? true, true),
            'theme_assets_obfuscate_js' => $this->resolveBool($attributes['theme_assets_obfuscate_js'] ?? $current['theme_assets_obfuscate_js'] ?? false, false),
            'theme_assets_obfuscation_preset' => $this->resolveThemeAssetObfuscationPreset((string) ($attributes['theme_assets_obfuscation_preset'] ?? $current['theme_assets_obfuscation_preset'] ?? 'balanced')),
            'theme_assets_combine_css' => $this->resolveBool($attributes['theme_assets_combine_css'] ?? $current['theme_assets_combine_css'] ?? true, true),
            'theme_assets_combine_js' => $this->resolveBool($attributes['theme_assets_combine_js'] ?? $current['theme_assets_combine_js'] ?? true, true),
            'theme_assets_separate_css' => $this->resolveBool($attributes['theme_assets_separate_css'] ?? $current['theme_assets_separate_css'] ?? false, false),
            'theme_assets_separate_js' => $this->resolveBool($attributes['theme_assets_separate_js'] ?? $current['theme_assets_separate_js'] ?? false, false),
            'theme_assets_defer_scripts' => $this->resolveBool($attributes['theme_assets_defer_scripts'] ?? $current['theme_assets_defer_scripts'] ?? true, true),
            'theme_assets_use_hash' => $this->resolveBool($attributes['theme_assets_use_hash'] ?? $current['theme_assets_use_hash'] ?? true, true),
            'cache_data_enabled' => $this->resolveBool($attributes['cache_data_enabled'] ?? $legacyCacheEnabled ?? $current['cache_data_enabled'] ?? $current['cache_enabled'] ?? true, true),
            'cache_response_enabled' => $this->resolveBool($attributes['cache_response_enabled'] ?? $legacyCacheEnabled ?? $current['cache_response_enabled'] ?? $current['cache_enabled'] ?? true, true),
            'cache_response_ttl' => $this->resolveCacheTtl($attributes['cache_response_ttl'] ?? $current['cache_response_ttl'] ?? 0),
            'security_rate_limit_enabled' => $this->resolveBool($attributes['security_rate_limit_enabled'] ?? $current['security_rate_limit_enabled'] ?? true, true),
            'security_rate_limit_per_minute' => $this->resolveBoundedInt($attributes['security_rate_limit_per_minute'] ?? $current['security_rate_limit_per_minute'] ?? 180, 30, 2000, 180),
            'security_rate_limit_burst_per_10s' => $this->resolveBoundedInt($attributes['security_rate_limit_burst_per_10s'] ?? $current['security_rate_limit_burst_per_10s'] ?? 45, 10, 1000, 45),
            'security_ip_ban_seconds' => $this->resolveBoundedInt($attributes['security_ip_ban_seconds'] ?? $current['security_ip_ban_seconds'] ?? 900, 60, 86400, 900),
            'security_login_max_attempts' => $this->resolveBoundedInt($attributes['security_login_max_attempts'] ?? $current['security_login_max_attempts'] ?? 5, 3, 20, 5),
            'security_login_decay_seconds' => $this->resolveBoundedInt($attributes['security_login_decay_seconds'] ?? $current['security_login_decay_seconds'] ?? 120, 30, 3600, 120),
            'security_login_ban_seconds' => $this->resolveBoundedInt($attributes['security_login_ban_seconds'] ?? $current['security_login_ban_seconds'] ?? 1800, 60, 86400, 1800),
            'security_emergency_mode' => $this->resolveBool($attributes['security_emergency_mode'] ?? $current['security_emergency_mode'] ?? false, false),
            'security_emergency_message' => $this->nullableString($attributes['security_emergency_message'] ?? null) ?? $current['security_emergency_message'] ?? (string) config('settings.defaults.security_emergency_message', 'Сайт временно недоступен.'),
        ]);

        $legacyPalette = isset($attributes['cms_palette'])
            ? (string) $attributes['cms_palette']
            : null;

        if (is_string($legacyPalette) && $legacyPalette !== '') {
            if (array_key_exists($legacyPalette, $this->lightPaletteOptions())) {
                $settings['admin_theme_mode'] = 'light';
                $settings['admin_light_palette'] = $legacyPalette;
            }

            if (array_key_exists($legacyPalette, $this->darkPaletteOptions())) {
                $settings['admin_theme_mode'] = 'dark';
                $settings['admin_dark_palette'] = $legacyPalette;
            }
        }

        $settings['cms_palette'] = $settings['admin_theme_mode'] === 'dark'
            ? $settings['admin_dark_palette']
            : $settings['admin_light_palette'];

        $settings['cache_enabled'] = $settings['cache_data_enabled'] && $settings['cache_response_enabled'];

        foreach ($settings as $key => $value) {
            $this->storeValue($key, $value);
        }

        $this->syncHomePage($settings['home_page_id']);

        $this->flushRuntimeCache();
        $this->cache->invalidateAll(reason: 'settings-updated');

        return $this->all();
    }

    public function syncHomePage(?int $pageId): void
    {
        Page::query()->where('is_home', true)->update(['is_home' => false]);

        if ($pageId !== null) {
            Page::query()->whereKey($pageId)->update(['is_home' => true]);
        }

        $this->storeValue('home_page_id', $pageId);
        $this->flushRuntimeCache();
    }

    public function rememberHomePage(?int $pageId): void
    {
        $this->storeValue('home_page_id', $pageId);
        $this->flushRuntimeCache();
    }

    public function themeViewPath(string $view = 'index.blade.php'): string
    {
        $themePath = base_path('themes/'.$this->activeTheme());

        if ($view === '' || $view === 'default') {
            $entry = $this->resolveThemeEntry($themePath);

            return $themePath.'/'.$entry;
        }

        if ($view !== 'index.blade.php' && str_ends_with($view, '.blade.php')) {
            return $themePath.'/'.$view;
        }

        if ($view !== 'index.blade.php') {
            $templateView = $this->resolveTemplateView($this->activeTheme(), $view);

            if ($templateView !== null) {
                return $themePath.'/'.$templateView;
            }
        }

        $entry = $this->resolveThemeEntry($themePath);

        return $themePath.'/'.$entry;
    }

    public function activeTheme(): string
    {
        return $this->all()['site_theme'];
    }

    public function publicPayload(): array
    {
        if ($this->publicPayloadCache !== null) {
            return $this->publicPayloadCache;
        }

        $settings = $this->all();
        $favicon = $settings['favicon_media_id'] !== null
            ? MediaFile::query()->find($settings['favicon_media_id'])
            : null;

        $this->publicPayloadCache = array_merge($settings, [
            'favicon_url' => $favicon?->variantUrl((string) config('media.preview_variant', 'thumbnail')) ?? $favicon?->url(),
            'admin_palette' => $this->resolveActivePalette($settings),
        ]);

        return $this->publicPayloadCache;
    }

    public function adminPayload(): array
    {
        $settings = $this->publicPayload();
        $currentFavicon = $settings['favicon_media_id'] !== null
            ? MediaFile::query()->find($settings['favicon_media_id'])
            : null;

        return [
            'settings' => $settings,
            'current_favicon' => $currentFavicon ? [
                'value' => $currentFavicon->id,
                'label' => $currentFavicon->title ?: $currentFavicon->original_name,
                'preview_url' => $currentFavicon->variantUrl((string) config('media.preview_variant', 'thumbnail')) ?? $currentFavicon->url(),
                'url' => $currentFavicon->url(),
            ] : null,
            'options' => [
                'date_formats' => $this->dateFormatOptions()->values()->all(),
                'time_formats' => $this->timeFormatOptions()->values()->all(),
                'themes' => $this->themeOptions(),
                'page_templates' => $this->pageTemplateOptions(),
                'media_variants' => $this->mediaVariantOptions($settings),
                'cms_palettes' => collect($this->paletteOptions())
                    ->map(fn (array $palette, string $key): array => [
                        'value' => $key,
                        'label' => $palette['label'],
                    ])
                    ->values()
                    ->all(),
                'admin_theme_modes' => [
                    ['value' => 'light', 'label' => 'Светлая'],
                    ['value' => 'dark', 'label' => 'Темная'],
                ],
                'admin_light_palettes' => collect($this->lightPaletteOptions())
                    ->map(fn (array $palette, string $key): array => [
                        'value' => $key,
                        'label' => $palette['label'],
                    ])
                    ->values()
                    ->all(),
                'admin_dark_palettes' => collect($this->darkPaletteOptions())
                    ->map(fn (array $palette, string $key): array => [
                        'value' => $key,
                        'label' => $palette['label'],
                    ])
                    ->values()
                    ->all(),
                'theme_asset_obfuscation_presets' => [
                    ['value' => 'safe', 'label' => 'Safe'],
                    ['value' => 'balanced', 'label' => 'Balanced'],
                    ['value' => 'aggressive', 'label' => 'Aggressive'],
                ],
                'home_pages' => Page::query()
                    ->orderBy('title')
                    ->get(['id', 'title', 'slug', 'is_home'])
                    ->map(fn (Page $page): array => [
                        'value' => $page->id,
                        'label' => $page->title,
                        'path' => $page->path,
                        'is_home' => (bool) $page->is_home,
                    ])
                    ->values()
                    ->all(),
                'favicon_files' => MediaFile::query()
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->map(fn (MediaFile $file): array => [
                        'value' => $file->id,
                        'label' => $file->title ?: $file->original_name,
                        'preview_url' => $file->variantUrl((string) config('media.preview_variant', 'thumbnail')) ?? $file->url(),
                        'url' => $file->url(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    protected function mediaVariantOptions(array $settings): array
    {
        $sizes = config('media.images.sizes', []);
        $options = [
            ['value' => 'original', 'label' => 'Оригинал'],
        ];

        if (($settings['media_image_optimize'] ?? true) && ($settings['media_image_keep_original'] ?? true)) {
            $options[] = ['value' => 'optimized', 'label' => 'Optimized'];
        }

        foreach ($sizes as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $width = Arr::get($definition, 'width');
            $height = Arr::get($definition, 'height');
            $mode = (string) Arr::get($definition, 'mode', 'resize');
            $dimensionLabel = collect([$width, $height])
                ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
                ->map(fn (mixed $value): string => (string) (int) $value)
                ->implode('x');

            $label = ucfirst((string) $name);

            if ($dimensionLabel !== '') {
                $label .= ' ('.$dimensionLabel.')';
            }

            if ($mode !== '') {
                $label .= ' · '.strtoupper($mode);
            }

            $options[] = [
                'value' => (string) $name,
                'label' => $label,
            ];
        }

        return $options;
    }

    public function dateFormatOptions(): Collection
    {
        return collect([
            ['value' => 'd.m.Y', 'label' => '28.04.2026'],
            ['value' => 'Y-m-d', 'label' => '2026-04-28'],
            ['value' => 'd/m/Y', 'label' => '28/04/2026'],
        ]);
    }

    public function timeFormatOptions(): Collection
    {
        return collect([
            ['value' => 'H:i', 'label' => '16:35'],
            ['value' => 'H:i:s', 'label' => '16:35:42'],
            ['value' => 'h:i A', 'label' => '04:35 PM'],
        ]);
    }

    protected function storeValue(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );

        $this->flushRuntimeCache();
    }

    protected function availableThemes(): array
    {
        $themes = collect($this->themeDefinitions())
            ->pluck('slug')
            ->values()
            ->all();

        return $themes !== [] ? $themes : ['default'];
    }

    protected function themeOptions(): array
    {
        return collect($this->themeDefinitions())
            ->map(fn (array $theme): array => [
                'value' => $theme['slug'],
                'label' => $theme['name'],
                'description' => $theme['description'],
            ])
            ->values()
            ->all();
    }

    protected function themeDefinitions(): array
    {
        if ($this->themeDefinitionsCache !== null) {
            return $this->themeDefinitionsCache;
        }

        $directories = glob(base_path('themes/*'), GLOB_ONLYDIR) ?: [];

        $this->themeDefinitionsCache = collect($directories)
            ->map(function (string $path): ?array {
                $slug = basename($path);

                $metadata = [];
                $metadataPath = $path.'/theme.json';

                if (is_file($metadataPath)) {
                    $decoded = json_decode((string) File::get($metadataPath), true);

                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                $entry = $this->resolveThemeEntry($path, $metadata);
                $entryPath = $path.'/'.$entry;

                if (! is_file($entryPath)) {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'name' => (string) ($metadata['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $slug))),
                    'description' => (string) ($metadata['description'] ?? ''),
                    'entry' => $entry,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $this->themeDefinitionsCache;
    }

    protected function flushRuntimeCache(): void
    {
        $this->allCache = null;
        $this->publicPayloadCache = null;
        $this->themeDefinitionsCache = null;
        $this->pageTemplateOptionsCache = [];
    }

    public function pageTemplateOptions(?string $theme = null): array
    {
        $theme = $theme ?? $this->activeTheme();

        if (array_key_exists($theme, $this->pageTemplateOptionsCache)) {
            return $this->pageTemplateOptionsCache[$theme];
        }

        $themePath = base_path('themes/'.$theme);
        $themeMetadata = [];
        $metadataPath = $themePath.'/theme.json';

        if (is_file($metadataPath)) {
            $decoded = json_decode((string) File::get($metadataPath), true);

            if (is_array($decoded)) {
                $themeMetadata = $decoded;
            }
        }

        $entry = $this->resolveThemeEntry($themePath, $themeMetadata);
        $options = collect([[
            'value' => 'default',
            'label' => 'По умолчанию',
            'description' => 'Основной шаблон темы',
            'view' => $entry,
        ]]);

        if (is_dir($themePath)) {
            $candidateFiles = collect(File::allFiles($themePath))
                ->map(fn ($file): string => $file->getPathname())
                ->filter(fn (string $filePath): bool => str_ends_with($filePath, '.blade.php'))
                ->all();

            foreach (array_unique($candidateFiles) as $filePath) {
                $relativePath = ltrim(str_replace($themePath, '', $filePath), '/');

                if ($relativePath === $entry || $relativePath === '404.blade.php') {
                    continue;
                }

                $template = $this->parseTemplateDefinition($filePath, $relativePath);

                if ($template !== null) {
                    $options->push($template);
                }
            }
        }

        $this->pageTemplateOptionsCache[$theme] = $options
            ->unique('value')
            ->values()
            ->all();

        return $this->pageTemplateOptionsCache[$theme];
    }

    protected function resolveTemplateView(string $theme, string $template): ?string
    {
        $template = trim($template);

        if ($template === '' || $template === 'default') {
            return null;
        }

        $definition = collect($this->pageTemplateOptions($theme))
            ->first(fn (array $option): bool => ($option['value'] ?? null) === $template);

        return is_array($definition) ? ($definition['view'] ?? null) : null;
    }

    protected function parseTemplateDefinition(string $filePath, string $relativePath): ?array
    {
        $snippet = collect(preg_split('/\r\n|\r|\n/', (string) File::get($filePath)) ?: [])
            ->take(12)
            ->implode("\n");

        preg_match('/CMS_TEMPLATE\s*:\s*([a-z0-9._-]+)(?:\|([^\n\r]+))?/i', $snippet, $templateMatch);
        preg_match('/CMS_TEMPLATE_DESCRIPTION\s*:\s*([^\n\r]+)/i', $snippet, $descriptionMatch);

        if (! isset($templateMatch[1])) {
            return null;
        }

        $relativeWithoutExtension = preg_replace('/\.blade\.php$/', '', $relativePath) ?? $relativePath;
        $fallbackValue = basename($relativeWithoutExtension);
        $fallbackLabel = ucfirst(str_replace(['-', '_'], ' ', $fallbackValue));
        $value = $this->sanitizeTemplateMarkerPart((string) ($templateMatch[1] ?? $fallbackValue));

        if ($value === '') {
            return null;
        }

        return [
            'value' => $value,
            'label' => $this->sanitizeTemplateMarkerPart((string) ($templateMatch[2] ?? $fallbackLabel)),
            'description' => $this->sanitizeTemplateMarkerPart((string) ($descriptionMatch[1] ?? 'Blade-файл '.$relativePath)),
            'view' => $relativePath,
        ];
    }

    protected function sanitizeTemplateMarkerPart(string $value): string
    {
        return trim(preg_replace('/\s*--}}\s*$/', '', $value) ?? $value);
    }

    protected function resolveTheme(string $theme): string
    {
        return in_array($theme, $this->availableThemes(), true) ? $theme : 'default';
    }

    protected function resolveThemeEntry(string $themePath, array $metadata = []): string
    {
        $entry = $metadata['entry'] ?? null;

        if (is_string($entry) && $entry !== '' && is_file($themePath.'/'.$entry)) {
            return $entry;
        }

        if (is_file($themePath.'/index.blade.php')) {
            return 'index.blade.php';
        }

        return 'theme.blade.php';
    }

    protected function resolveThemeMode(string $mode): string
    {
        return in_array($mode, ['light', 'dark'], true) ? $mode : 'dark';
    }

    protected function resolveLightPalette(string $palette): string
    {
        return array_key_exists($palette, $this->lightPaletteOptions()) ? $palette : 'slate';
    }

    protected function resolveDarkPalette(string $palette): string
    {
        return array_key_exists($palette, $this->darkPaletteOptions()) ? $palette : 'midnight';
    }

    protected function resolveActivePalette(array $settings): array
    {
        $mode = (string) ($settings['admin_theme_mode'] ?? 'dark');
        $paletteKey = $mode === 'dark'
            ? (string) ($settings['admin_dark_palette'] ?? 'midnight')
            : (string) ($settings['admin_light_palette'] ?? 'slate');

        return $this->paletteOptions()[$paletteKey] ?? $this->paletteOptions()['midnight'];
    }

    protected function resolveDateFormat(string $format): string
    {
        return $this->dateFormatOptions()->pluck('value')->contains($format) ? $format : 'd.m.Y';
    }

    protected function resolveTimeFormat(string $format): string
    {
        return $this->timeFormatOptions()->pluck('value')->contains($format) ? $format : 'H:i';
    }

    protected function resolveMediaVariant(string $variant): string
    {
        return in_array($variant, ['original', 'large', 'medium', 'thumb'], true) ? $variant : 'original';
    }

    protected function resolveThemeAssetObfuscationPreset(string $preset): string
    {
        return in_array($preset, ['safe', 'balanced', 'aggressive'], true) ? $preset : 'balanced';
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

    protected function resolveCacheTtl(mixed $ttl): int
    {
        if (! is_numeric($ttl)) {
            return 0;
        }

        return max(0, (int) $ttl);
    }

    protected function resolveBoundedInt(mixed $value, int $min, int $max, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    protected function paletteOptions(): array
    {
        return $this->lightPaletteOptions() + $this->darkPaletteOptions();
    }

    protected function lightPaletteOptions(): array
    {
        return [
            'slate' => [
                'label' => 'Slate',
                'variables' => [
                    '--admin-color-bg' => '#f8fafc',
                    '--admin-color-surface' => '#ffffff',
                    '--admin-color-surface-muted' => '#f9fafb',
                    '--admin-color-border' => '#e5e7eb',
                    '--admin-color-border-strong' => '#d1d5db',
                    '--admin-color-text' => '#111827',
                    '--admin-color-text-muted' => '#6b7280',
                    '--admin-color-primary' => '#111827',
                    '--admin-color-primary-contrast' => '#ffffff',
                    '--admin-color-danger' => '#b91c1c',
                    '--admin-color-danger-bg' => '#fff5f5',
                    '--admin-color-danger-border' => '#fecaca',
                    '--admin-color-info-bg' => '#e5eefc',
                    '--admin-color-info-text' => '#1d4ed8',
                ],
            ],
            'sand' => [
                'label' => 'Sand',
                'variables' => [
                    '--admin-color-bg' => '#f5efe5',
                    '--admin-color-surface' => '#fffaf2',
                    '--admin-color-surface-muted' => '#f3eadc',
                    '--admin-color-border' => '#d8ccb8',
                    '--admin-color-border-strong' => '#c9b79a',
                    '--admin-color-text' => '#2f241c',
                    '--admin-color-text-muted' => '#7a6858',
                    '--admin-color-primary' => '#8b5e34',
                    '--admin-color-primary-contrast' => '#fffaf2',
                    '--admin-color-danger' => '#a63b1f',
                    '--admin-color-danger-bg' => '#fff1ea',
                    '--admin-color-danger-border' => '#f2c3b1',
                    '--admin-color-info-bg' => '#efe4d5',
                    '--admin-color-info-text' => '#7a4b1d',
                ],
            ],
            'forest' => [
                'label' => 'Forest',
                'variables' => [
                    '--admin-color-bg' => '#eff5f0',
                    '--admin-color-surface' => '#fbfefb',
                    '--admin-color-surface-muted' => '#eef6ef',
                    '--admin-color-border' => '#cfe0d2',
                    '--admin-color-border-strong' => '#afc8b5',
                    '--admin-color-text' => '#173226',
                    '--admin-color-text-muted' => '#5c7768',
                    '--admin-color-primary' => '#285943',
                    '--admin-color-primary-contrast' => '#f5fbf7',
                    '--admin-color-danger' => '#9a2f2f',
                    '--admin-color-danger-bg' => '#fff2f2',
                    '--admin-color-danger-border' => '#efc5c5',
                    '--admin-color-info-bg' => '#e1efe6',
                    '--admin-color-info-text' => '#1f6b4a',
                ],
            ],
        ];
    }

    protected function darkPaletteOptions(): array
    {
        return [
            'midnight' => [
                'label' => 'Midnight',
                'variables' => [
                    '--admin-color-bg' => '#071223',
                    '--admin-color-surface' => '#0b1b34',
                    '--admin-color-surface-muted' => '#132744',
                    '--admin-color-border' => '#1f365b',
                    '--admin-color-border-strong' => '#2c4a78',
                    '--admin-color-text' => '#e7efff',
                    '--admin-color-text-muted' => '#9fb3d8',
                    '--admin-color-primary' => '#2d62ff',
                    '--admin-color-primary-contrast' => '#ffffff',
                    '--admin-color-danger' => '#ff7a7a',
                    '--admin-color-danger-bg' => '#3a1e2b',
                    '--admin-color-danger-border' => '#6b3147',
                    '--admin-color-info-bg' => '#1e3c73',
                    '--admin-color-info-text' => '#dce8ff',
                ],
            ],
            'ocean' => [
                'label' => 'Ocean',
                'variables' => [
                    '--admin-color-bg' => '#061a1f',
                    '--admin-color-surface' => '#0c2a31',
                    '--admin-color-surface-muted' => '#123942',
                    '--admin-color-border' => '#23505c',
                    '--admin-color-border-strong' => '#2d6472',
                    '--admin-color-text' => '#e0fbff',
                    '--admin-color-text-muted' => '#9cc8cf',
                    '--admin-color-primary' => '#19c2d8',
                    '--admin-color-primary-contrast' => '#052228',
                    '--admin-color-danger' => '#ff8c8c',
                    '--admin-color-danger-bg' => '#3f2626',
                    '--admin-color-danger-border' => '#744343',
                    '--admin-color-info-bg' => '#1a4b59',
                    '--admin-color-info-text' => '#d5f8ff',
                ],
            ],
            'graphite' => [
                'label' => 'Graphite',
                'variables' => [
                    '--admin-color-bg' => '#121416',
                    '--admin-color-surface' => '#1b1f23',
                    '--admin-color-surface-muted' => '#23282e',
                    '--admin-color-border' => '#343a42',
                    '--admin-color-border-strong' => '#454d57',
                    '--admin-color-text' => '#eef1f5',
                    '--admin-color-text-muted' => '#a7afbb',
                    '--admin-color-primary' => '#f0b429',
                    '--admin-color-primary-contrast' => '#1f1a0d',
                    '--admin-color-danger' => '#ff9f92',
                    '--admin-color-danger-bg' => '#3d2a29',
                    '--admin-color-danger-border' => '#6b4340',
                    '--admin-color-info-bg' => '#3a3f46',
                    '--admin-color-info-text' => '#f2f5f9',
                ],
            ],
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}