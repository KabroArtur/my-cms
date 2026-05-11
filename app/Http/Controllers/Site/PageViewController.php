<?php

namespace App\Http\Controllers\Site;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Support\Services\CmsCacheService;
use App\Core\Themes\Services\ThemeRuntime;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Контроллер отдает публичный вывод страниц сайта.
 * Он находит домашнюю или slug-страницу и передает ее в активную тему.
 */
class PageViewController extends Controller
{
    public function __construct(
        protected SettingsManager $settings,
        protected ThemeRuntime $cms,
        protected CmsCacheService $cache,
    ) {
    }

    /**
     * Контроллер показывает домашнюю страницу сайта.
     */
    public function home(PageRepository $pages): Response
    {
        if (! $this->shouldUseResponseCache()) {
            $page = $pages->findHomePage();

            if ($page === null) {
                throw new NotFoundHttpException('Домашняя страница не найдена.');
            }

            return response($this->renderThemeHtml($page));
        }

        $html = $this->cache->rememberSite(
            key: 'public-response:home:'.app()->getLocale().':'.$this->settings->activeTheme(),
            resolver: function () use ($pages): string {
                $page = $pages->findHomePage();

                if ($page === null) {
                    throw new NotFoundHttpException('Домашняя страница не найдена.');
                }

                return $this->renderThemeHtml($page);
            },
            ttlSeconds: $this->responseCacheTtl(),
        );

        return response($html);
    }

    /**
     * Контроллер показывает публичную страницу по slug.
     */
    public function show(string $slugPath, PageRepository $pages): Response
    {
        $slugPath = trim($slugPath, '/');

        if (! $this->shouldUseResponseCache()) {
            $page = $pages->findPublicBySlug($slugPath);

            if ($page === null) {
                throw new NotFoundHttpException('Страница не найдена.');
            }

            return response($this->renderThemeHtml($page));
        }

        $html = $this->cache->rememberSite(
            key: 'public-response:slug:'.($slugPath !== '' ? $slugPath : '/').':'.app()->getLocale().':'.$this->settings->activeTheme(),
            resolver: function () use ($pages, $slugPath): string {
                $page = $pages->findPublicBySlug($slugPath);

                if ($page === null) {
                    throw new NotFoundHttpException('Страница не найдена.');
                }

                return $this->renderThemeHtml($page);
            },
            ttlSeconds: $this->responseCacheTtl(),
        );

        return response($html);
    }

    /**
     * Контроллер рендерит страницу через blade-файл темы.
     */
    protected function renderThemeHtml(mixed $page): string
    {
        $themeSettings = $this->settings->publicPayload();
        $themePath = $this->settings->themeViewPath();

        view()->replaceNamespace('theme', dirname($themePath));

        return (string) view()->file($themePath, [
            'page' => $page,
            'settings' => $themeSettings,
            'cms' => $this->cms->usePage($page),
        ])->render();
    }

    protected function shouldUseResponseCache(): bool
    {
        return ! app()->runningUnitTests() && $this->cache->isResponseCacheEnabled();
    }

    protected function responseCacheTtl(): int
    {
        return $this->cache->responseCacheTtl();
    }
}