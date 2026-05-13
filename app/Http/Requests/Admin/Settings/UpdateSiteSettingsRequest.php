<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $favicon = $this->input('favicon_media_id');
        $siteDefaultFeaturedMedia = $this->input('site_default_featured_media_id');
        $cacheTtl = $this->input('cache_response_ttl');
        $legacyCacheEnabled = $this->input('cache_enabled');
        $adminEntryPath = $this->input('admin_entry_path');
        $merge = [];

        if ($favicon === null || $favicon === '' || $favicon === 'null' || ! is_numeric($favicon)) {
            $merge['favicon_media_id'] = null;
        }

        if ($siteDefaultFeaturedMedia === null || $siteDefaultFeaturedMedia === '' || $siteDefaultFeaturedMedia === 'null' || ! is_numeric($siteDefaultFeaturedMedia)) {
            $merge['site_default_featured_media_id'] = null;
        }

        if ($cacheTtl === null || $cacheTtl === '' || ! is_numeric($cacheTtl)) {
            $merge['cache_response_ttl'] = 0;
        }

        if (is_string($adminEntryPath)) {
            $merge['admin_entry_path'] = strtolower(trim(trim($adminEntryPath), '/'));
        }

        if (! $this->has('cache_data_enabled') && $legacyCacheEnabled !== null) {
            $merge['cache_data_enabled'] = $legacyCacheEnabled;
        }

        if (! $this->has('cache_response_enabled') && $legacyCacheEnabled !== null) {
            $merge['cache_response_enabled'] = $legacyCacheEnabled;
        }

        if (is_array($this->input('home_page_ids'))) {
            $merge['home_page_ids'] = collect($this->input('home_page_ids'))
                ->map(function (mixed $pageId): ?int {
                    if ($pageId === null || $pageId === '' || $pageId === 'null') {
                        return null;
                    }

                    return is_numeric($pageId) ? (int) $pageId : null;
                })
                ->all();
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('settings.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'date_format' => ['required', 'string', 'max:30'],
            'time_format' => ['required', 'string', 'max:30'],
            'home_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'home_page_ids' => ['nullable', 'array'],
            'home_page_ids.*' => ['nullable', 'integer', 'exists:pages,id'],
            'site_theme' => ['required', 'string', 'max:120'],
            'site_default_featured_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'site_featured_media_variant' => ['required', 'string', 'max:20'],
            'media_default_insert_variant' => ['required', 'string', 'max:20'],
            'media_image_optimize' => ['nullable', 'boolean'],
            'media_image_max_width' => ['nullable', 'integer', 'min:320', 'max:12000'],
            'media_image_max_height' => ['nullable', 'integer', 'min:320', 'max:12000'],
            'media_image_jpg_quality' => ['nullable', 'integer', 'min:30', 'max:100'],
            'media_image_webp_quality' => ['nullable', 'integer', 'min:30', 'max:100'],
            'media_image_convert_to_webp' => ['nullable', 'boolean'],
            'media_image_keep_original' => ['nullable', 'boolean'],
            'media_image_create_thumbnails' => ['nullable', 'boolean'],
            'admin_theme_mode' => ['nullable', 'string', Rule::in(['light', 'dark'])],
            'admin_light_palette' => ['nullable', 'string', Rule::in(['slate', 'sand', 'forest'])],
            'admin_dark_palette' => ['nullable', 'string', Rule::in(['midnight', 'ocean', 'graphite'])],
            'cms_palette' => ['required', 'string', 'max:50'],
            'theme_assets_minify_css' => ['nullable', 'boolean'],
            'theme_assets_minify_js' => ['nullable', 'boolean'],
            'theme_assets_obfuscate_js' => ['nullable', 'boolean'],
            'theme_assets_obfuscation_preset' => ['nullable', 'string', Rule::in(['safe', 'balanced', 'aggressive'])],
            'theme_assets_combine_css' => ['nullable', 'boolean'],
            'theme_assets_combine_js' => ['nullable', 'boolean'],
            'theme_assets_separate_css' => ['nullable', 'boolean'],
            'theme_assets_separate_js' => ['nullable', 'boolean'],
            'theme_assets_defer_scripts' => ['nullable', 'boolean'],
            'theme_assets_use_hash' => ['nullable', 'boolean'],
            'cache_enabled' => ['nullable', 'boolean'],
            'cache_data_enabled' => ['nullable', 'boolean'],
            'cache_response_enabled' => ['nullable', 'boolean'],
            'cache_response_ttl' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'seo_allow_indexing' => ['nullable', 'boolean'],
            'seo_allow_following' => ['nullable', 'boolean'],
            'seo_sitemap_enabled' => ['nullable', 'boolean'],
            'seo_sitemap_change_frequency' => ['nullable', 'string', Rule::in(['hourly', 'daily', 'weekly', 'monthly', 'yearly'])],
            'seo_canonical_scheme' => ['nullable', 'string', Rule::in(['https', 'http'])],
            'seo_canonical_www_mode' => ['nullable', 'string', Rule::in(['preserve', 'with_www', 'without_www'])],
            'seo_trailing_slash' => ['nullable', 'boolean'],
            'seo_open_graph_enabled' => ['nullable', 'boolean'],
            'seo_social_networks' => ['nullable', 'array'],
            'seo_social_networks.*' => ['string', Rule::in(['facebook', 'x', 'linkedin', 'telegram', 'whatsapp'])],
            'seo_hreflang_enabled' => ['nullable', 'boolean'],
            'seo_favicon_enabled' => ['nullable', 'boolean'],
            'seo_sitemap_excluded_paths' => ['nullable', 'string', 'max:4000'],
            'seo_robots_custom_rules' => ['nullable', 'string', 'max:4000'],
            'security_rate_limit_enabled' => ['nullable', 'boolean'],
            'security_rate_limit_per_minute' => ['nullable', 'integer', 'min:30', 'max:2000'],
            'security_rate_limit_burst_per_10s' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'security_ip_ban_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'security_login_max_attempts' => ['nullable', 'integer', 'min:3', 'max:20'],
            'security_login_decay_seconds' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'security_login_ban_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'security_emergency_mode' => ['nullable', 'boolean'],
            'security_emergency_message' => ['nullable', 'string', 'max:500'],
            'admin_entry_path' => [
                'nullable',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn((array) config('cms.restricted_admin_paths', [])),
            ],
        ];
    }
}