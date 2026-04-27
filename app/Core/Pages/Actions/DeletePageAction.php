<?php

namespace App\Core\Pages\Actions;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Models\Page;

/**
 * Action удаляет страницу через доменный контракт репозитория.
 * Он станет точкой для будущих проверок перед удалением записи.
 */
class DeletePageAction
{
    public function __construct(
        protected PageRepository $pages,
    ) {
    }

    /**
     * Action удаляет переданную страницу.
     */
    public function handle(Page $page): void
    {
        $this->pages->delete($page);
    }
}