<?php

namespace App\Providers;

use App\Core\Media\Models\MediaFile;
use App\Core\Media\Models\MediaFolder;
use App\Core\Roles\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Core\Pages\Models\Page;
use App\Policies\MediaFilePolicy;
use App\Policies\MediaFolderPolicy;
use App\Policies\PagePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Core\Security\Services\SecurityRuntimeSettings;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->syncConfiguredPermissions();

        RateLimiter::for('auth-login', function (Request $request): array {
            $security = app(SecurityRuntimeSettings::class)->all();
            $maxAttempts = max(3, (int) ($security['security_login_max_attempts'] ?? 5));
            $decaySeconds = max(30, (int) ($security['security_login_decay_seconds'] ?? 120));

            return [
                Limit::perMinutes(max(1, (int) ceil($decaySeconds / 60)), $maxAttempts)
                    ->by((string) $request->ip().'|'.(string) $request->input('login')),
            ];
        });

        RateLimiter::for('admin-api', function (Request $request): array {
            $security = app(SecurityRuntimeSettings::class)->all();
            $limit = max(60, (int) ($security['security_rate_limit_per_minute'] ?? 180));

            return [
                Limit::perMinute($limit)
                    ->by((string) ($request->user()?->id ?? $request->ip() ?? 'guest')),
            ];
        });

        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(MediaFolder::class, MediaFolderPolicy::class);
        Gate::policy(MediaFile::class, MediaFilePolicy::class);
    }

    private function syncConfiguredPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $configured = collect(config('access.permissions', []))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '');

        foreach ($configured as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => Str::headline(str_replace('.', ' ', $slug))],
            );
        }
    }
}
