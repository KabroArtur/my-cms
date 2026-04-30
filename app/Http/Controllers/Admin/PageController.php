<?php

namespace App\Http\Controllers\Admin;

use App\Core\Pages\Actions\CreatePageAction;
use App\Core\Pages\Actions\DeletePageAction;
use App\Core\Pages\Actions\UpdatePageAction;
use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;
use App\Core\Pages\Services\AdditionalFieldsService;
use App\Core\Security\Services\SecurityAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\StorePageRequest;
use App\Http\Requests\Admin\Pages\UpdatePageRequest;
use App\Http\Resources\Admin\PageResource;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Контроллер отдает административный API для страниц.
 * Он держит тонкий слой между HTTP и доменом Pages.
 */
class PageController extends Controller
{
    /**
     * Контроллер возвращает список страниц для админки.
     */
    public function index(PageRepository $pages): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        return PageResource::collection($pages->paginate());
    }

    /**
     * Контроллер возвращает корзину страниц для админки.
     */
    public function trash(PageRepository $pages): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        return PageResource::collection($pages->paginateTrashed());
    }

    /**
     * Контроллер возвращает все страницы для визуального дерева меню.
     */
    public function tree(PageRepository $pages): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Page::class);

        return PageResource::collection($pages->all());
    }

    /**
     * Контроллер отдает одну страницу для детального экрана админки.
     */
    public function show(Page $page): PageResource
    {
        Gate::authorize('viewAny', Page::class);

        return PageResource::make($page);
    }

    /**
     * Контроллер создает новую страницу через доменный action.
     */
    public function store(StorePageRequest $request, CreatePageAction $createPage, AdditionalFieldsService $additionalFields, SecurityAuditLogger $audit): JsonResponse
    {
        $page = $createPage->handle(PageData::fromArray($request->validated()));

        if ($page->created_by === null && $request->user() !== null) {
            $page->forceFill(['created_by' => $request->user()->id])->save();
            $page = $page->fresh(['parent', 'featuredMedia', 'creator']);
        }

        $additionalFields->syncPageValues($page, (array) $request->input('additional_fields', []));

        $audit->log('pages.created', $request->user(), [
            'target_page_id' => $page->id,
            'target_page_slug' => $page->slug,
            'target_page_path' => $page->path,
        ]);

        return PageResource::make($page)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет существующую страницу через доменный action.
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePageAction $updatePage, AdditionalFieldsService $additionalFields, SecurityAuditLogger $audit): PageResource
    {
        $page = $updatePage->handle($page, PageData::fromArray($request->validated()));
        $additionalFields->syncPageValues($page, (array) $request->input('additional_fields', []));

        $audit->log('pages.updated', $request->user(), [
            'target_page_id' => $page->id,
            'target_page_slug' => $page->slug,
            'target_page_path' => $page->path,
        ]);

        return PageResource::make($page);
    }

    /**
     * Контроллер удаляет существующую страницу через доменный action.
     */
    public function destroy(Page $page, DeletePageAction $deletePage, SecurityAuditLogger $audit): Response
    {
        $this->authorize('delete', $page);

        $audit->log('pages.deleted', request()->user(), [
            'target_page_id' => $page->id,
            'target_page_slug' => $page->slug,
            'target_page_path' => $page->path,
        ]);

        $deletePage->handle($page);

        return response()->noContent();
    }

    /**
     * Контроллер восстанавливает страницу из корзины.
     */
    public function restore(int $page, PageRepository $pages, SecurityAuditLogger $audit): PageResource
    {
        $trashedPage = $pages->findTrashedById($page);

        abort_if($trashedPage === null, 404);

        $this->authorize('update', $trashedPage);

        $restoredPage = $pages->restore($trashedPage);

        $audit->log('pages.restored', request()->user(), [
            'target_page_id' => $restoredPage->id,
            'target_page_slug' => $restoredPage->slug,
            'target_page_path' => $restoredPage->path,
        ]);

        return PageResource::make($restoredPage);
    }

    /**
     * Контроллер удаляет страницу из корзины безвозвратно.
     */
    public function forceDelete(int $page, PageRepository $pages, SecurityAuditLogger $audit): Response
    {
        $trashedPage = $pages->findTrashedById($page);

        abort_if($trashedPage === null, 404);

        $this->authorize('delete', $trashedPage);

        $audit->log('pages.force_deleted', request()->user(), [
            'target_page_id' => $trashedPage->id,
            'target_page_slug' => $trashedPage->slug,
            'target_page_path' => $trashedPage->path,
        ]);

        $pages->forceDelete($trashedPage);

        return response()->noContent();
    }

    /**
     * Контроллер сохраняет полную иерархию страниц для будущего меню.
     */
    public function updateTree(Request $request, PageRepository $pages): Response
    {
        Gate::authorize('reorder', Page::class);

        $validated = $request->validate([
            'tree' => ['required', 'array', 'min:1'],
            'tree.*.id' => ['required', 'integer'],
            'tree.*.parent_id' => ['nullable', 'integer'],
            'tree.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $pages->syncTree($validated['tree']);

        return response()->noContent();
    }
}