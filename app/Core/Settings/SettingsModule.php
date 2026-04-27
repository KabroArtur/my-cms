<?php

namespace App\Core\Settings;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль настроек станет единым местом для конфигурации CMS.
 * Он позже объединит системные параметры, группы настроек и кеширование.
 */
class SettingsModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'settings',
            name: 'Settings',
            description: 'Модуль отвечает за системные настройки и конфигурацию.',
        );
    }
}