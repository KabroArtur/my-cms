<?php

namespace App\Core\Support;

use App\Core\Support\Contracts\CoreModule;

/**
 * Менеджер дает единый прикладной вход в слой Core.
 * Он скрывает детали реестра и упрощает работу другим слоям CMS.
 */
class CoreManager
{
    public function __construct(
        protected CoreRegistry $registry,
    ) {
    }

    /**
     * Менеджер возвращает все подключенные доменные модули.
     *
     * @return array<int, CoreModule>
     */
    public function modules(): array
    {
        return $this->registry->all();
    }

    /**
     * Менеджер возвращает описания модулей для интерфейсов и диагностики.
     *
     * @return array<int, ModuleDefinition>
     */
    public function definitions(): array
    {
        return $this->registry->definitions();
    }

    /**
     * Менеджер проверяет доступность доменного модуля по его ключу.
     */
    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    /**
     * Менеджер возвращает модуль по ключу для точечного вызова.
     */
    public function module(string $key): ?CoreModule
    {
        return $this->registry->find($key);
    }
}