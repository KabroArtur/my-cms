<?php

namespace App\Core\Users;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль пользователей станет доменной зоной управления аккаунтами.
 * Он позже соберет профиль, статус и административные сценарии работы.
 */
class UsersModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'users',
            name: 'Users',
            description: 'Модуль отвечает за пользователей и их состояние.',
        );
    }
}