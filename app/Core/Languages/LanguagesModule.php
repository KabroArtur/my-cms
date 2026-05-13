<?php

namespace App\Core\Languages;

use App\Core\Languages\Services\LanguageManager;
use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

class LanguagesModule extends BaseCoreModule
{
    public function register(): void
    {
        $this->app->singleton(LanguageManager::class, LanguageManager::class);
    }

    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'languages',
            name: 'Languages',
            description: 'Модуль отвечает за языки сайта и мультиязычность страниц.',
        );
    }
}