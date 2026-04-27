<?php

namespace App\Core\Support\Contracts;

use App\Core\Support\ModuleDefinition;

/**
 * Модуль описывает отдельную доменную зону CMS.
 * Он сообщает системе свои метаданные и точки расширения.
 */
interface CoreModule
{
    /**
     * Модуль возвращает описание для реестра и диагностики.
     */
    public function definition(): ModuleDefinition;

    /**
     * Модуль регистрирует контейнер и зависимости своей зоны.
     */
    public function register(): void;

    /**
     * Модуль запускает действия после инициализации приложения.
     */
    public function boot(): void;
}