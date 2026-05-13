<?php

namespace App\Core\Languages\Services;

use App\Core\Languages\Models\Language;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LanguageManager
{
    protected ?Collection $allCache = null;

    protected ?Collection $activeCache = null;

    protected ?Language $defaultCache = null;

    protected ?Language $currentCache = null;

    public function all(): Collection
    {
        if ($this->allCache !== null) {
            return $this->allCache;
        }

        if (! $this->tableExists()) {
            return new Collection();
        }

        return $this->allCache = Language::query()->ordered()->get();
    }

    public function active(): Collection
    {
        if ($this->activeCache !== null) {
            return $this->activeCache;
        }

        if (! $this->tableExists()) {
            return new Collection();
        }

        return $this->activeCache = $this->all()
            ->filter(fn (Language $language): bool => (bool) $language->is_active)
            ->values();
    }

    public function default(): ?Language
    {
        if ($this->defaultCache !== null) {
            return $this->defaultCache;
        }

        $default = $this->all()->first(fn (Language $language): bool => (bool) $language->is_default);

        if ($default instanceof Language) {
            return $this->defaultCache = $default;
        }

        $fallback = $this->all()->first();

        return $this->defaultCache = $fallback instanceof Language ? $fallback : null;
    }

    public function defaultId(): ?int
    {
        return $this->default()?->id;
    }

    public function findById(?int $id): ?Language
    {
        if ($id === null) {
            return null;
        }

        return $this->all()->first(fn (Language $language): bool => $language->id === $id);
    }

    public function findByCode(?string $code, bool $activeOnly = false): ?Language
    {
        $normalized = strtolower(trim((string) $code));

        if ($normalized === '') {
            return null;
        }

        $source = $activeOnly ? $this->active() : $this->all();

        return $source->first(fn (Language $language): bool => strtolower($language->code) === $normalized);
    }

    public function resolveForPage(?Page $page = null, ?string $requestedCode = null): ?Language
    {
        if ($page?->language_id !== null) {
            return $this->findById((int) $page->language_id) ?? $this->default();
        }

        return $this->findByCode($requestedCode, activeOnly: true) ?? $this->default();
    }

    public function setCurrent(?Language $language): ?Language
    {
        $language ??= $this->default();
        $this->currentCache = $language;

        if ($language !== null) {
            app()->setLocale($language->code);
        }

        return $language;
    }

    public function current(): ?Language
    {
        return $this->currentCache ?? $this->default();
    }

    public function routePattern(bool $excludeDefault = false): string
    {
        $defaultCode = strtolower((string) ($this->default()?->code ?? ''));
        $codes = $this->active()
            ->map(fn (Language $language): string => strtolower($language->code))
            ->filter(fn (string $code): bool => $code !== '' && (! $excludeDefault || $code !== $defaultCode))
            ->values();

        if ($codes->isEmpty()) {
            $codes = collect(config('cms.languages.bootstrap', []))
                ->map(function (mixed $language) use ($excludeDefault): ?string {
                    if (! is_array($language)) {
                        return null;
                    }

                    $code = strtolower(trim((string) ($language['code'] ?? '')));
                    $isDefault = (bool) ($language['is_default'] ?? false);

                    if ($code === '' || ($excludeDefault && $isDefault)) {
                        return null;
                    }

                    return $code;
                })
                ->filter()
                ->values();
        }

        if ($codes->isEmpty()) {
            return 'a^';
        }

        return implode('|', $codes->map(fn (string $code): string => preg_quote($code, '/'))->all());
    }

    public function htmlLang(?Language $language = null): string
    {
        $language ??= $this->current() ?? $this->default();

        if ($language === null) {
            return app()->getLocale();
        }

        return str_replace('_', '-', $language->locale ?: $language->code);
    }

    public function usesPrefix(Language $language): bool
    {
        $default = $this->default();

        return $default === null || $default->id !== $language->id;
    }

    public function pageRelativeUrl(Page $page, ?Language $language = null): string
    {
        $language ??= $this->findById((int) $page->language_id) ?? $this->default();
        $path = trim((string) $page->path, '/');

        if ($language === null) {
            return $page->is_home ? '/' : '/'.$path;
        }

        $segments = [];

        if ($this->usesPrefix($language)) {
            $segments[] = $language->code;
        }

        if (! $page->is_home && $path !== '') {
            $segments[] = $path;
        }

        $relativePath = $segments === [] ? '/' : '/'.implode('/', $segments);

        if ($page->is_home && $segments !== []) {
            $relativePath = rtrim($relativePath, '/').'/';
        }

        return app(SettingsManager::class)->normalizePublicPath($relativePath, (bool) $page->is_home);
    }

    public function translationGroupId(?string $value = null): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : (string) Str::uuid();
    }

    public function create(array $attributes): Language
    {
        $language = Language::query()->create($this->normalizeAttributes($attributes));
        $this->syncDefaultLanguage($language, (bool) $language->is_default);
        $this->flushCache();

        return $language->fresh();
    }

    public function update(Language $language, array $attributes): Language
    {
        $normalized = $this->normalizeAttributes($attributes, $language);

        if (($language->is_default || ($normalized['is_default'] ?? false)) && ! ($normalized['is_active'] ?? true)) {
            throw ValidationException::withMessages([
                'is_active' => __('languages.messages.inactive_default'),
            ]);
        }

        $language->fill($normalized);
        $language->save();

        $this->syncDefaultLanguage($language, (bool) $language->is_default);
        $this->flushCache();

        return $language->fresh();
    }

    public function delete(Language $language): void
    {
        if ($language->pages()->exists()) {
            throw ValidationException::withMessages([
                'language' => __('languages.messages.delete_in_use'),
            ]);
        }

        $wasDefault = (bool) $language->is_default;
        $language->delete();

        if ($wasDefault) {
            $fallback = Language::query()->oldest('id')->first();

            if ($fallback !== null) {
                $fallback->forceFill(['is_default' => true, 'is_active' => true])->saveQuietly();
            }
        }

        $this->flushCache();
    }

    protected function normalizeAttributes(array $attributes, ?Language $language = null): array
    {
        $normalized = [
            'name' => trim((string) ($attributes['name'] ?? $language?->name ?? '')),
            'native_name' => trim((string) ($attributes['native_name'] ?? $language?->native_name ?? '')),
            'code' => strtolower(trim((string) ($attributes['code'] ?? $language?->code ?? ''))),
            'locale' => trim((string) ($attributes['locale'] ?? $language?->locale ?? '')),
            'direction' => in_array(($attributes['direction'] ?? $language?->direction ?? 'ltr'), ['ltr', 'rtl'], true)
                ? (string) ($attributes['direction'] ?? $language?->direction ?? 'ltr')
                : 'ltr',
            'is_default' => (bool) ($attributes['is_default'] ?? $language?->is_default ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? $language?->is_active ?? true),
            'sort_order' => isset($attributes['sort_order']) ? (int) $attributes['sort_order'] : (int) ($language?->sort_order ?? 0),
        ];

        if ($normalized['is_default']) {
            $normalized['is_active'] = true;
        }

        return $normalized;
    }

    protected function syncDefaultLanguage(Language $language, bool $shouldBeDefault): void
    {
        if ($shouldBeDefault) {
            Language::query()
                ->whereKeyNot($language->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            if (! $language->is_default || ! $language->is_active) {
                $language->forceFill(['is_default' => true, 'is_active' => true])->saveQuietly();
            }

            return;
        }

        if (Language::query()->where('is_default', true)->exists()) {
            return;
        }

        $fallback = Language::query()->oldest('id')->first();

        if ($fallback !== null) {
            $fallback->forceFill(['is_default' => true, 'is_active' => true])->saveQuietly();
        }
    }

    protected function flushCache(): void
    {
        $this->allCache = null;
        $this->activeCache = null;
        $this->defaultCache = null;
        $this->currentCache = null;
    }

    protected function tableExists(): bool
    {
        return Schema::hasTable('languages');
    }
}