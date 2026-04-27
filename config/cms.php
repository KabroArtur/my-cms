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
];