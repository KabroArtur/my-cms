<?php

namespace App\Providers;

use App\Core\Modules\Services\PluginManager;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booted(function (): void {
            $manager = $this->app->make(PluginManager::class);

            foreach ($manager->enabledProviders() as $providerClass) {
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        });
    }
}
