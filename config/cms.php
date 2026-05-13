<?php

use App\Core\Auth\AuthModule;
use App\Core\Backup\BackupModule;
use App\Core\Languages\LanguagesModule;
use App\Core\Media\MediaModule;
use App\Core\Modules\ModulesModule;
use App\Core\Pages\PagesModule;
use App\Core\Roles\RolesModule;
use App\Core\Security\SecurityModule;
use App\Core\Settings\SettingsModule;
use App\Core\Themes\ThemesModule;
use App\Core\Users\UsersModule;

return [

    /*
    |--------------------------------------------------------------------------
    | Core Modules
    |--------------------------------------------------------------------------
    |
    | Система хранит список доменных модулей в конфиге.
    | Это упрощает расширение CMS без правки bootstrap-слоя.
    |
    */

    'modules' => [
        AuthModule::class,
        UsersModule::class,
        RolesModule::class,
        SecurityModule::class,
        LanguagesModule::class,
        PagesModule::class,
        MediaModule::class,
        ThemesModule::class,
        SettingsModule::class,
        ModulesModule::class,
        BackupModule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Macro Prefix
    |--------------------------------------------------------------------------
    |
    | Публичные helper-макросы тем регистрируются с этим префиксом.
    | Сейчас по умолчанию это cms_, но позже можно перейти на другой префикс.
    | После изменения значения нужно очистить кэш конфигурации приложения.
    |
    */

    'theme_macro_prefix' => env('CMS_THEME_MACRO_PREFIX', 'cms_'),

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Languages
    |--------------------------------------------------------------------------
    |
    | Эти коды используются до того, как таблица languages станет доступной.
    | Это нужно для ранней регистрации локализованных маршрутов в тестах и
    | при первом bootstrap без прогретой базы.
    |
    */

    'languages' => [
        'bootstrap' => [
            ['code' => 'uk', 'is_default' => true],
            ['code' => 'en', 'is_default' => false],
            ['code' => 'ru', 'is_default' => false],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Response Cache TTL
    |--------------------------------------------------------------------------
    |
    | TTL для полного кэша публичного HTML-ответа в секундах.
    | 0 = rememberForever (рекомендуется при version-based invalidation).
    |
    */

    'response_cache_ttl' => (int) env('CMS_RESPONSE_CACHE_TTL', 0),

    /*
    |--------------------------------------------------------------------------
    | Shared Hosting Flat Public Disk
    |--------------------------------------------------------------------------
    |
    | В fallback-режиме shared hosting публичные theme assets и media
    | генерируются в корень проекта, а не в стандартный public disk.
    | Значение выносится в config, чтобы корректно работать с config cache.
    |
    */

    'shared_hosting_flat_public_disk' => (bool) env('SHARED_HOSTING_FLAT_PUBLIC_DISK', false),

    /*
    |--------------------------------------------------------------------------
    | Admin Entry Path
    |--------------------------------------------------------------------------
    |
    | Базовый URL входа в административную часть.
    | Значение можно переопределить через настройки (в зашифрованном виде).
    |
    */

    'admin_path' => (string) env('CMS_ADMIN_PATH', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Admin Path Grace Redirect Window
    |--------------------------------------------------------------------------
    |
    | После смены admin-пути старый URL можно держать как redirect ограниченное
    | время, чтобы администратор не потерял доступ.
    |
    */

    'admin_path_grace_seconds' => (int) env('CMS_ADMIN_PATH_GRACE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Restricted Admin Paths
    |--------------------------------------------------------------------------
    |
    | Пути, которые нельзя использовать как точку входа в админ-панель.
    |
    */

    'restricted_admin_paths' => [
        '',
        'api',
        'login',
        'logout',
        'storage',
        'assets',
        'wp-admin',
        'wp-login',
        'administrator',
        'backend',
        'panel',
        'cpanel',
        'cms',
        'joomla',
        'drupal',
        'ghost',
        'strapi',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Runtime Controls
    |--------------------------------------------------------------------------
    |
    | Настройки runtime-защиты от DDoS/bruteforce и аварийного режима.
    |
    */

    'security' => [
        'rate_limit_enabled' => true,
        'rate_limit_per_minute' => 180,
        'rate_limit_burst_per_10s' => 45,
        'ip_ban_seconds' => 900,
        'login_max_attempts' => 5,
        'login_decay_seconds' => 120,
        'login_ban_seconds' => 1800,
        'emergency_mode' => false,
        'emergency_message' => 'Сайт временно переведен в аварийный режим обслуживания. Попробуйте позже.',
    ],
];