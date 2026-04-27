<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Конфиг хранит стабильные ключи разрешений CMS.
    | Они используются в policy, seed и будущих административных настройках.
    |
    */

    'permissions' => [
        'pages.view',
        'pages.create',
        'pages.update',
        'pages.delete',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'settings.view',
        'settings.update',
        'media.view',
        'media.create',
        'media.update',
        'media.delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Конфиг описывает базовые роли и связанные разрешения.
    | Это дает системе одну точку для начальной матрицы доступов.
    |
    */

    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'permissions' => ['*'],
        ],
        'editor' => [
            'name' => 'Editor',
            'permissions' => [
                'pages.view',
                'pages.create',
                'pages.update',
                'media.view',
                'media.create',
                'media.update',
            ],
        ],
    ],
];