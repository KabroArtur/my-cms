<?php

namespace App\Core\Support;

/**
 * Объект хранит краткое описание доменного модуля.
 * Он помогает держать единый формат для всех зон CMS.
 */
readonly class ModuleDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
    ) {
    }
}