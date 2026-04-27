<?php

namespace App\Core\Support\Console;

use App\Core\Support\CoreManager;
use Illuminate\Console\Command;

/**
 * Команда показывает текущий состав Core.
 * Она помогает быстро проверить, какие домены реально подключены в CMS.
 */
class ListCoreModulesCommand extends Command
{
    /**
     * Команда выводит список модулей Core.
     * Она нужна для простой диагностики архитектурного слоя.
     *
     * @var string
     */
    protected $signature = 'cms:core';

    /**
     * Команда кратко объясняет свое назначение в консоли.
     *
     * @var string
     */
    protected $description = 'Показывает зарегистрированные Core-модули CMS';

    public function handle(CoreManager $core): int
    {
        $definitions = $core->definitions();

        if ($definitions === []) {
            $this->warn('Core-модули не зарегистрированы.');

            return self::SUCCESS;
        }

        $this->table(
            ['Ключ', 'Имя', 'Описание'],
            array_map(
                static fn ($definition): array => [
                    $definition->key,
                    $definition->name,
                    $definition->description,
                ],
                $definitions,
            ),
        );

        return self::SUCCESS;
    }
}