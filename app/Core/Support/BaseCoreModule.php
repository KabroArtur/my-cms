<?php

namespace App\Core\Support;

use App\Core\Support\Contracts\CoreModule;
use Illuminate\Contracts\Foundation\Application;

/**
 * Базовый модуль задает общий шаблон для всех доменных зон.
 * Он снимает лишний шум и оставляет точку роста в одном месте.
 */
abstract class BaseCoreModule implements CoreModule
{
    public function __construct(
        protected Application $app,
    ) {
    }

    /**
     * Модуль возвращает свое описание через единый объект.
     */
    abstract protected function newDefinition(): ModuleDefinition;

    public function definition(): ModuleDefinition
    {
        return $this->newDefinition();
    }

    /**
     * Модуль по умолчанию ничего не регистрирует.
     * Домен переопределяет метод только при реальной необходимости.
     */
    public function register(): void
    {
    }

    /**
     * Модуль по умолчанию ничего не запускает.
     * Домен добавляет поведение только когда оно действительно появится.
     */
    public function boot(): void
    {
    }
}