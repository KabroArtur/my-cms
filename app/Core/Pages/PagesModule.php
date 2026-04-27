<?php

namespace App\Core\Pages;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Repositories\EloquentPageRepository;
use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль страниц станет главным контентным слоем CMS.
 * Он позже объединит дерево страниц, шаблоны и публикацию контента.
 */
class PagesModule extends BaseCoreModule
{
    /**
     * Модуль связывает доменные контракты Pages с их реализациями.
     */
    public function register(): void
    {
        $this->app->bind(PageRepository::class, EloquentPageRepository::class);
    }

    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'pages',
            name: 'Pages',
            description: 'Модуль отвечает за страницы и контентную структуру.',
        );
    }
}