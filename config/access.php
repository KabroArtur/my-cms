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
        'pages.access',
        'pages.create',
        'pages.update',
        'pages.delete',
        'pages.additional_fields.manage',
        'users.access',
        'users.create',
        'users.update',
        'users.delete',
        'users.manage_roles',
        'roles.access',
        'roles.create',
        'roles.update',
        'settings.access',
        'settings.general.manage',
        'settings.appearance.manage',
        'settings.cache.manage',
        'settings.security.manage',
        'plugins.access',
        'plugins.install',
        'plugins.enable',
        'plugins.delete',
        'media.access',
        'media.upload',
        'media.delete',
        'media.manage_folders',
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
                'pages.access',
                'pages.create',
                'pages.update',
                'pages.delete',
                'pages.additional_fields.manage',
                'settings.access',
                'settings.general.manage',
                'settings.appearance.manage',
                'settings.cache.manage',
                'settings.security.manage',
                'plugins.access',
                'users.access',
                'users.create',
                'roles.access',
                'roles.create',
                'media.access',
                'media.upload',
                'media.delete',
                'media.manage_folders',
            ],
        ],
    ],

    'protected_roles' => [
        'admin',
        'editor',
    ],

    'protected_usernames' => [
        'admin',
    ],
];