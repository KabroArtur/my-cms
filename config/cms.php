<?php

use App\Core\Auth\AuthModule;
use App\Core\Backup\BackupModule;
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