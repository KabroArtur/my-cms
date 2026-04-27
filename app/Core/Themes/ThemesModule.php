<?php

namespace App\Core\Themes;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль тем станет слоем для управления внешним видом сайта.
 * Он позже объединит активную тему, шаблоны и тему по умолчанию.
 */
class ThemesModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'themes',
            name: 'Themes',
            description: 'Модуль отвечает за темы, шаблоны и активный frontend.',
        );
    }
}