<?php

namespace App\Core\Settings\Services;

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class SettingsManager
{
    public function all(): array
    {
        $stored = Setting::query()
            ->get(['key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [
                $setting->key => json_decode((string) $setting->value, true),
            ])
            ->all();

        $settings = array_merge(config('settings.defaults', []), $stored);
        $settings['site_theme'] = $this->resolveTheme((string) ($settings['site_theme'] ?? 'default'));
        $settings['cms_palette'] = $this->resolvePalette((string) ($settings['cms_palette'] ?? 'slate'));
        $settings['site_featured_media_variant'] = $this->resolveMediaVariant((string) ($settings['site_featured_media_variant'] ?? 'original'));
        $settings['media_default_insert_variant'] = $this->resolveMediaVariant((string) ($settings['media_default_insert_variant'] ?? 'original'));

        $homePageId = Arr::get($settings, 'home_page_id');
        $settings['home_page_id'] = is_numeric($homePageId) ? (int) $homePageId : null;

        $faviconId = Arr::get($settings, 'favicon_media_id');
        $settings['favicon_media_id'] = is_numeric($faviconId) ? (int) $faviconId : null;

        return $settings;
    }

    public function update(array $attributes): array
    {
        $settings = array_merge($this->all(), [
            'site_name' => $this->nullableString($attributes['site_name'] ?? null) ?? (string) config('settings.defaults.site_name', 'My CMS'),
            'favicon_media_id' => $this->nullableInt($attributes['favicon_media_id'] ?? null),
            'date_format' => $this->resolveDateFormat((string) ($attributes['date_format'] ?? config('settings.defaults.date_format', 'd.m.Y'))),
            'time_format' => $this->resolveTimeFormat((string) ($attributes['time_format'] ?? config('settings.defaults.time_format', 'H:i'))),
            'home_page_id' => $this->nullableInt($attributes['home_page_id'] ?? null),
            'site_theme' => $this->resolveTheme((string) ($attributes['site_theme'] ?? 'default')),
            'site_featured_media_variant' => $this->resolveMediaVariant((string) ($attributes['site_featured_media_variant'] ?? 'original')),
            'media_default_insert_variant' => $this->resolveMediaVariant((string) ($attributes['media_default_insert_variant'] ?? 'original')),
            'cms_palette' => $this->resolvePalette((string) ($attributes['cms_palette'] ?? 'slate')),
        ]);

        foreach ($settings as $key => $value) {
            $this->storeValue($key, $value);
        }

        $this->syncHomePage($settings['home_page_id']);

        return $this->all();
    }

    public function syncHomePage(?int $pageId): void
    {
        Page::query()->where('is_home', true)->update(['is_home' => false]);

        if ($pageId !== null) {
            Page::query()->whereKey($pageId)->update(['is_home' => true]);
        }

        $this->storeValue('home_page_id', $pageId);
    }

    public function rememberHomePage(?int $pageId): void
    {
        $this->storeValue('home_page_id', $pageId);
    }

    public function themeViewPath(string $view = 'theme.blade.php'): string
    {
        return base_path('themes/'.$this->activeTheme().'/'.$view);
    }

    public function activeTheme(): string
    {
        return $this->all()['site_theme'];
    }

    public function publicPayload(): array
    {
        $settings = $this->all();
        $favicon = $settings['favicon_media_id'] !== null
            ? MediaFile::query()->find($settings['favicon_media_id'])
            : null;

        return array_merge($settings, [
            'favicon_url' => $favicon?->variantUrl('thumb') ?? $favicon?->url(),
            'admin_palette' => $this->paletteOptions()[$settings['cms_palette']] ?? $this->paletteOptions()['slate'],
        ]);
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
                'preview_url' => $currentFavicon->variantUrl('thumb') ?? $currentFavicon->url(),
                'url' => $currentFavicon->url(),
            ] : null,
            'options' => [
                'date_formats' => $this->dateFormatOptions()->values()->all(),
                'time_formats' => $this->timeFormatOptions()->values()->all(),
                'themes' => $this->themeOptions(),
                'media_variants' => [
                    ['value' => 'original', 'label' => 'Оригинал'],
                    ['value' => 'large', 'label' => 'Large'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'thumb', 'label' => 'Mini / Thumb'],
                ],
                'cms_palettes' => collect($this->paletteOptions())
                    ->map(fn (array $palette, string $key): array => [
                        'value' => $key,
                        'label' => $palette['label'],
                    ])
                    ->values()
                    ->all(),
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
                        'preview_url' => $file->variantUrl('thumb') ?? $file->url(),
                        'url' => $file->url(),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
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
        $directories = glob(base_path('themes/*'), GLOB_ONLYDIR) ?: [];

        return collect($directories)
            ->map(function (string $path): ?array {
                $slug = basename($path);
                $entryPath = $path.'/theme.blade.php';

                if (! is_file($entryPath)) {
                    return null;
                }

                $metadata = [];
                $metadataPath = $path.'/theme.json';

                if (is_file($metadataPath)) {
                    $decoded = json_decode((string) File::get($metadataPath), true);

                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }

                return [
                    'slug' => $slug,
                    'name' => (string) ($metadata['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $slug))),
                    'description' => (string) ($metadata['description'] ?? ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function resolveTheme(string $theme): string
    {
        return in_array($theme, $this->availableThemes(), true) ? $theme : 'default';
    }

    protected function resolvePalette(string $palette): string
    {
        return array_key_exists($palette, $this->paletteOptions()) ? $palette : 'slate';
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

    protected function paletteOptions(): array
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