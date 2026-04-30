<?php

namespace App\Core\Modules\Services;

use App\Core\Modules\Models\Plugin;
use App\Core\Security\Services\AdminPathManager;
use App\Core\Roles\Models\Permission;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PluginManager
{
    private const STATUS_NOT_INSTALLED = 'not_installed';
    private const STATUS_INSTALLED = 'installed';
    private const STATUS_ENABLED = 'enabled';
    private const STATUS_DISABLED = 'disabled';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $manifests = collect($this->scanManifests())
            ->mapWithKeys(fn (array $manifest): array => [$manifest['slug'] => $manifest]);

        $states = $this->canUseDatabase()
            ? Plugin::query()->get()->keyBy('slug')
            : collect();

        return $manifests
            ->map(function (array $manifest, string $slug) use ($states): array {
                $state = $states->get($slug);
                $status = $state?->status ?? self::STATUS_NOT_INSTALLED;

                return [
                    'slug' => $slug,
                    'name' => $manifest['name'],
                    'description' => $manifest['description'] ?? null,
                    'version' => $manifest['version'] ?? '1.0.0',
                    'status' => $status,
                    'installed' => in_array($status, [self::STATUS_INSTALLED, self::STATUS_ENABLED, self::STATUS_DISABLED], true),
                    'enabled' => $status === self::STATUS_ENABLED,
                    'provider' => $manifest['provider'] ?? null,
                    'admin_menu' => Arr::get($manifest, 'admin_menu', []),
                    'permissions' => Arr::get($manifest, 'permissions', []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function enabledProviders(): array
    {
        return collect($this->enabledPlugins())
            ->pluck('provider')
            ->filter(fn (mixed $provider): bool => is_string($provider) && $provider !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function enabledRouteFiles(string $type): array
    {
        return collect($this->enabledPlugins())
            ->map(function (array $plugin) use ($type): ?string {
                $path = $this->pluginBasePath($plugin['slug']).'/routes/'.$type.'.php';

                if (! File::exists($path)) {
                    return null;
                }

                return $path;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function enabledAdminMenuForUser(callable $permissionResolver): array
    {
        return collect($this->enabledPlugins())
            ->flatMap(function (array $plugin) use ($permissionResolver): array {
                $items = Arr::get($plugin, 'admin_menu', []);

                if (! is_array($items)) {
                    return [];
                }

                return collect($items)
                    ->filter(function (mixed $item) use ($permissionResolver): bool {
                        if (! is_array($item)) {
                            return false;
                        }

                        $permission = Arr::get($item, 'permission');

                        if (! is_string($permission) || $permission === '') {
                            return true;
                        }

                        return (bool) $permissionResolver($permission);
                    })
                    ->map(fn (array $item): array => [
                        'title' => Arr::get($item, 'title', 'Plugin'),
                        'path' => $this->resolveMenuPath(Arr::get($item, 'path', '/')),
                        'group' => Arr::get($item, 'group'),
                        'subgroup' => Arr::get($item, 'subgroup'),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    public function install(string $slug): array
    {
        $manifest = $this->manifestBySlug($slug);
        $now = Carbon::now();

        $record = Plugin::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $manifest['name'],
                'version' => $manifest['version'] ?? '1.0.0',
                'status' => self::STATUS_INSTALLED,
                'installed_at' => $now,
                'disabled_at' => null,
                'meta' => ['manifest' => $manifest],
            ],
        );

        return $record->toArray();
    }

    public function enable(string $slug): array
    {
        $manifest = $this->manifestBySlug($slug);
        $record = Plugin::query()->where('slug', $slug)->first();

        if ($record === null) {
            throw new RuntimeException('Плагин не установлен. Сначала выполните установку.');
        }

        $this->runPluginMigrations($slug);
        $this->syncPluginPermissions($manifest, true);

        $record->fill([
            'name' => $manifest['name'],
            'version' => $manifest['version'] ?? '1.0.0',
            'status' => self::STATUS_ENABLED,
            'enabled_at' => Carbon::now(),
            'disabled_at' => null,
            'meta' => ['manifest' => $manifest],
        ])->save();

        return $record->toArray();
    }

    public function disable(string $slug): array
    {
        $record = Plugin::query()->where('slug', $slug)->first();

        if ($record === null) {
            throw new RuntimeException('Плагин не установлен.');
        }

        $record->fill([
            'status' => self::STATUS_DISABLED,
            'disabled_at' => Carbon::now(),
        ])->save();

        return $record->toArray();
    }

    public function delete(string $slug, bool $dropData = false, bool $removeFiles = false): void
    {
        $manifest = $this->manifestBySlug($slug);

        if ($dropData) {
            $this->rollbackPluginMigrations($slug);
            $this->syncPluginPermissions($manifest, false);
        }

        Plugin::query()->where('slug', $slug)->delete();

        if ($removeFiles) {
            File::deleteDirectory($this->pluginBasePath($slug));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifestBySlug(string $slug): array
    {
        foreach ($this->scanManifests() as $manifest) {
            if (($manifest['slug'] ?? null) === $slug) {
                return $manifest;
            }
        }

        throw new RuntimeException('Манифест плагина не найден.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function enabledPlugins(): array
    {
        $enabledSlugs = $this->canUseDatabase()
            ? Plugin::query()->where('status', self::STATUS_ENABLED)->pluck('slug')->all()
            : [];

        if ($enabledSlugs === []) {
            return [];
        }

        return collect($this->scanManifests())
            ->filter(fn (array $manifest): bool => in_array($manifest['slug'], $enabledSlugs, true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanManifests(): array
    {
        $pluginsDir = base_path('plugins');

        if (! File::isDirectory($pluginsDir)) {
            return [];
        }

        return collect(File::directories($pluginsDir))
            ->map(function (string $directory): ?array {
                $manifestPath = $directory.'/plugin.json';

                if (! File::exists($manifestPath)) {
                    return null;
                }

                $manifest = json_decode((string) File::get($manifestPath), true);

                if (! is_array($manifest)) {
                    return null;
                }

                $manifest['slug'] = $manifest['slug'] ?? basename($directory);

                return $manifest;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function pluginBasePath(string $slug): string
    {
        return base_path('plugins/'.$slug);
    }

    private function canUseDatabase(): bool
    {
        try {
            return Schema::hasTable('plugins');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function syncPluginPermissions(array $manifest, bool $create): void
    {
        $permissions = Arr::get($manifest, 'permissions', []);

        if (! is_array($permissions)) {
            return;
        }

        foreach ($permissions as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            if ($create) {
                Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => str($slug)->replace('.', ' ')->headline()->value()],
                );
            } else {
                Permission::query()->where('slug', $slug)->delete();
            }
        }
    }

    private function runPluginMigrations(string $slug): void
    {
        $migrationsPath = 'plugins/'.$slug.'/database/migrations';

        if (! File::isDirectory(base_path($migrationsPath))) {
            return;
        }

        Artisan::call('migrate', ['--path' => $migrationsPath, '--force' => true]);
    }

    private function rollbackPluginMigrations(string $slug): void
    {
        $migrationsPath = 'plugins/'.$slug.'/database/migrations';

        if (! File::isDirectory(base_path($migrationsPath))) {
            return;
        }

        Artisan::call('migrate:rollback', ['--path' => $migrationsPath, '--force' => true]);
    }

    private function resolveMenuPath(mixed $rawPath): string
    {
        $path = is_string($rawPath) && $rawPath !== '' ? $rawPath : '/';

        if (! str_starts_with($path, 'admin://')) {
            return $path;
        }

        $suffix = ltrim(substr($path, strlen('admin://')), '/');
        $adminPath = app(AdminPathManager::class)->currentPath();

        return '/'.$adminPath.($suffix !== '' ? '/'.$suffix : '');
    }
}
