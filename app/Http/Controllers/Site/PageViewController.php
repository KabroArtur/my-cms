<?php

namespace App\Http\Controllers\Site;

use App\Core\Pages\Contracts\PageRepository;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер отдает публичный вывод страниц сайта.
 * Он находит домашнюю или slug-страницу и передает ее в активную тему.
 */
class PageViewController extends Controller
{
    /**
     * Контроллер показывает домашнюю страницу сайта.
     */
    public function home(PageRepository $pages): Response
    {
        $page = $pages->findHomePage();

        if ($page === null) {
            throw new NotFoundHttpException('Домашняя страница не найдена.');
        }

        return $this->renderTheme($page);
    }

    /**
     * Контроллер показывает публичную страницу по slug.
     */
    public function show(string $slugPath, PageRepository $pages): Response
    {
        $page = $pages->findPublicBySlug(trim($slugPath, '/'));

        if ($page === null) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        return $this->renderTheme($page);
    }

    /**
     * Контроллер рендерит страницу через blade-файл темы.
     */
    protected function renderTheme(mixed $page): Response
    {
        /** @var View $view */
        $view = view()->file(base_path('themes/default/theme.blade.php'), [
            'page' => $page,
        ]);

        return response($view);
    }
}