<?php

namespace App\Core\Media;

use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;

/**
 * Модуль медиа станет основой для библиотеки файлов CMS.
 * Он позже объединит загрузку, хранение и преобразование ресурсов.
 */
class MediaModule extends BaseCoreModule
{
    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'media',
            name: 'Media',
            description: 'Модуль отвечает за файлы, изображения и их хранение.',
        );
    }
}