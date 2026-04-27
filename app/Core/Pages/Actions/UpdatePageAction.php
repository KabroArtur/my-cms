<?php

namespace App\Core\Pages\Actions;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;

/**
 * Action обновляет страницу через доменный контракт репозитория.
 * Он станет точкой для будущих правил редактирования и событий домена.
 */
class UpdatePageAction
{
    public function __construct(
        protected PageRepository $pages,
    ) {
    }

    /**
     * Action принимает текущую страницу и DTO с новыми данными.
     */
    public function handle(Page $page, PageData $data): Page
    {
        return $this->pages->update($page, $data);
    }
}