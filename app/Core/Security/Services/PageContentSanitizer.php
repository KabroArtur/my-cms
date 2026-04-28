<?php

namespace App\Core\Security\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Сервис очищает HTML-контент страниц от XSS payload без полного отказа от форматирования.
 * Он оставляет безопасную разметку и ссылки, но убирает script/event/javascript-сценарии.
 */
class PageContentSanitizer
{
    protected HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias();

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $sanitized = trim($this->sanitizer->sanitize($content));

        return $sanitized === '' ? null : $sanitized;
    }
}