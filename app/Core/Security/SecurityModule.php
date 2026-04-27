<?php

namespace App\Core\Security;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль безопасности выделяет отдельный слой для критичных правил CMS.
 * Он позже соберет аудит, ограничения и защиту административной зоны.
 */
class SecurityModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'security',
            name: 'Security',
            description: 'Модуль отвечает за защиту, аудит и критичные проверки.',
        );
    }
}