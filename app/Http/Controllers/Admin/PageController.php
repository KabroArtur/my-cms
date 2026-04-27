<?php

namespace App\Http\Controllers\Admin;

use App\Core\Pages\Actions\CreatePageAction;
use App\Core\Pages\Actions\DeletePageAction;
use App\Core\Pages\Actions\UpdatePageAction;
use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Data\PageData;
use App\Core\Pages\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pages\StorePageRequest;
use App\Http\Requests\Admin\Pages\UpdatePageRequest;
use App\Http\Resources\Admin\PageResource;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
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
     * Контроллер создает новую страницу через доменный action.
     */
    public function store(StorePageRequest $request, CreatePageAction $createPage): JsonResponse
    {
        $page = $createPage->handle(PageData::fromArray($request->validated()));

        return PageResource::make($page)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Контроллер обновляет существующую страницу через доменный action.
     */
    public function update(UpdatePageRequest $request, Page $page, UpdatePageAction $updatePage): PageResource
    {
        $page = $updatePage->handle($page, PageData::fromArray($request->validated()));

        return PageResource::make($page);
    }

    /**
     * Контроллер удаляет существующую страницу через доменный action.
     */
    public function destroy(Page $page, DeletePageAction $deletePage): Response
    {
        $this->authorize('delete', $page);

        $deletePage->handle($page);

        return response()->noContent();
    }
}