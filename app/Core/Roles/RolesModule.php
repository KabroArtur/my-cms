<?php

namespace App\Core\Roles;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль ролей будет хранить матрицу прав для сотрудников CMS.
 * Он позже разделит роли, разрешения и политику доступа.
 */
class RolesModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'roles',
            name: 'Roles',
            description: 'Модуль отвечает за роли и наборы разрешений.',
        );
    }
}