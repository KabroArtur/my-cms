<?php

namespace App\Core\Pages\Enums;

/**
 * Видимость страницы задает, можно ли показывать ее в публичном слое.
 * Она помогает отделить открытые страницы от закрытых записей.
 */
enum PageVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}