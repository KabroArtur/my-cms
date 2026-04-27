<?php

namespace App\Core\Support;

use App\Core\Support\Console\ListCoreModulesCommand;
use App\Core\Support\Contracts\CoreModule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Провайдер собирает базовый Core CMS в одном bootstrap-слое.
 * Он держит список доменов и единый порядок их запуска.
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Провайдер читает доменные модули из конфига CMS.
     * Это убирает жесткую привязку bootstrap-слоя к конкретным папкам.
     *
     * @return array<int, class-string<CoreModule>>
     */
    protected function moduleClasses(): array
    {
        /** @var array<int, class-string<CoreModule>> $modules */
        $modules = config('cms.modules', []);

        return $modules;
    }

    /**
     * Провайдер сначала строит реестр и дает каждому модулю зарегистрировать зависимости.
     */
    public function register(): void
    {
        $this->app->singleton(CoreManager::class, fn (Application $app): CoreManager => new CoreManager(
            $app->make(CoreRegistry::class),
        ));

        $this->app->singleton(CoreRegistry::class, function (Application $app): CoreRegistry {
            $registry = new CoreRegistry();

            foreach ($this->moduleClasses() as $moduleClass) {
                /** @var CoreModule $module */
                $module = $app->make($moduleClass);
                $module->register();
                $registry->register($module);
            }

            return $registry;
        });
    }

    /**
     * После регистрации провайдер проходит по модулям и запускает их boot-цикл.
     */
    public function boot(CoreRegistry $registry): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListCoreModulesCommand::class,
            ]);
        }

        foreach ($registry->all() as $module) {
            $module->boot();
        }
    }
}