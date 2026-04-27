<?php

namespace App\Core\Auth;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль хранит будущую точку входа для аутентификации CMS.
 * Он будет объединять guard, login-flow и восстановление доступа.
 */
class AuthModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'auth',
            name: 'Auth',
            description: 'Модуль отвечает за аутентификацию и сценарии входа.',
        );
    }
}