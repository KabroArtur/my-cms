<?php

namespace App\Core\Pages\Enums;

/**
 * Статус страницы задает ее жизненный цикл в CMS.
 * Он помогает держать публикацию и черновики в явном виде.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}