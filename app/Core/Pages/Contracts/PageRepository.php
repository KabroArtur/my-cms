<?php

namespace App\Core\Pages\Contracts;

use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Репозиторий задает единый вход к хранилищу страниц.
 * Он отделяет доменную логику от конкретного способа доступа к данным.
 */
interface PageRepository
{
    /**
     * Репозиторий возвращает список страниц для административного интерфейса.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Репозиторий ищет страницу по идентификатору.
     */
    public function findById(int $id): ?Page;

    /**
     * Репозиторий ищет страницу по slug.
     */
    public function findBySlug(string $slug): ?Page;

    /**
     * Репозиторий создает новую страницу.
     */
    public function create(PageData $data): Page;

    /**
     * Репозиторий обновляет существующую страницу.
     */
    public function update(Page $page, PageData $data): Page;

    /**
     * Репозиторий удаляет существующую страницу.
     */
    public function delete(Page $page): void;
}