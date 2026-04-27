<?php

namespace App\Core\Modules;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль расширений станет управляющим слоем для внешних пакетов CMS.
 * Он позже будет отвечать за установку, активацию и состояние модулей.
 */
class ModulesModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'modules',
            name: 'Modules',
            description: 'Модуль отвечает за расширения и их жизненный цикл.',
        );
    }
}