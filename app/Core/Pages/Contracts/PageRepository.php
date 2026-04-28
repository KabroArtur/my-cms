<?php

namespace App\Core\Pages\Contracts;

use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
     * Репозиторий возвращает корзину страниц для административного интерфейса.
     */
    public function paginateTrashed(int $perPage = 15): LengthAwarePaginator;

    /**
     * Репозиторий возвращает все страницы для построения дерева.
     *
     * @return Collection<int, Page>
     */
    public function all(): Collection;

    /**
     * Репозиторий ищет страницу по идентификатору.
     */
    public function findById(int $id): ?Page;

    /**
     * Репозиторий ищет страницу в корзине по идентификатору.
     */
    public function findTrashedById(int $id): ?Page;

    /**
     * Репозиторий ищет страницу по slug.
     */
    public function findBySlug(string $slug): ?Page;

    /**
     * Репозиторий ищет домашнюю страницу публичного сайта.
     */
    public function findHomePage(): ?Page;

    /**
     * Репозиторий ищет публичную опубликованную страницу по slug.
     */
    public function findPublicBySlug(string $slug): ?Page;

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

    /**
     * Репозиторий восстанавливает страницу из корзины.
     */
    public function restore(Page $page): Page;

    /**
     * Репозиторий удаляет страницу безвозвратно.
     */
    public function forceDelete(Page $page): void;

    /**
     * Репозиторий сохраняет полное дерево страниц.
     *
     * @param array<int, array{id:int, parent_id:int|null, sort_order:int}> $nodes
     */
    public function syncTree(array $nodes): void;
}