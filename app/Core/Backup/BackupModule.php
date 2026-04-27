<?php

namespace App\Core\Backup;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль резервного копирования держит место для backup-стратегии.
 * Он позже соберет экспорт файлов, базы и расписание задач.
 */
class BackupModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'backup',
            name: 'Backup',
            description: 'Модуль отвечает за резервные копии и восстановление.',
        );
    }
}