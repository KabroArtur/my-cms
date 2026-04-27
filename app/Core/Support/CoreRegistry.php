<?php

namespace App\Core\Support;

use App\Core\Support\Contracts\CoreModule;

/**
 * Реестр собирает все модули Core в одном месте.
 * Он дает простой вход для диагностики и будущей оркестрации CMS.
 */
class CoreRegistry
{
    /**
     * @var array<string, CoreModule>
     */
    protected array $modules = [];

    /**
     * Реестр добавляет модуль по его стабильному ключу.
     */
    public function register(CoreModule $module): void
    {
        $this->modules[$module->definition()->key] = $module;
    }

    /**
     * Реестр возвращает модули в порядке их регистрации.
     * Это удобно для bootstrap-цикла и отладки.
     *
     * @return array<int, CoreModule>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    /**
     * Реестр возвращает только описания модулей.
     * Это удобно для панели администратора и health-check в будущем.
     *
     * @return array<int, ModuleDefinition>
     */
    public function definitions(): array
    {
        return array_map(
            static fn (CoreModule $module): ModuleDefinition => $module->definition(),
            $this->all(),
        );
    }

    /**
     * Реестр проверяет наличие модуля по его ключу.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->modules);
    }

    /**
     * Реестр возвращает модуль по ключу или null.
     */
    public function find(string $key): ?CoreModule
    {
        return $this->modules[$key] ?? null;
    }
}