<?php

namespace App\Core\Pages\Actions;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;

/**
 * Action создает страницу через доменный контракт репозитория.
 * Он станет точкой для будущих правил, событий и валидации сценария.
 */
class CreatePageAction
{
    public function __construct(
        protected PageRepository $pages,
    ) {
    }

    /**
     * Action принимает DTO и создает новую страницу.
     */
    public function handle(PageData $data): Page
    {
        return $this->pages->create($data);
    }
}