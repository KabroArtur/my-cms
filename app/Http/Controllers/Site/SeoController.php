<?php

namespace App\Http\Controllers\Site;

use App\Core\Languages\Services\LanguageManager;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    protected const SITEMAP_CHUNK_SIZE = 500;

    public function __construct(
        protected LanguageManager $languages,
        protected SettingsManager $settings,
    ) {
    }

    public function robots(): Response
    {
        $settings = $this->settings->publicPayload();
        $allowIndexing = (bool) ($settings['seo_allow_indexing'] ?? true);
        $sitemapEnabled = (bool) ($settings['seo_sitemap_enabled'] ?? true);
        $customRules = trim((string) ($settings['seo_robots_custom_rules'] ?? ''));

        $lines = ['User-agent: *'];
        $lines[] = $allowIndexing ? 'Disallow:' : 'Disallow: /';

        if ($customRules !== '') {
            $lines[] = '';
            $lines[] = $customRules;
        }

        if ($sitemapEnabled) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.$this->settings->canonicalUrl('/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        $settings = $this->settings->publicPayload();

        if (! ($settings['seo_sitemap_enabled'] ?? true)) {
            abort(404);
        }

        $pages = $this->publishedPages();
        $chunks = $pages->chunk(self::SITEMAP_CHUNK_SIZE)->values();

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($chunks as $index => $chunk) {
            $sitemapNumber = $index + 1;
            $lastModified = $chunk->max(fn (Page $page) => ($page->updated_at ?? $page->published_at ?? now())->getTimestamp()) ?? now()->getTimestamp();

            $xml[] = '  <sitemap>';
            $xml[] = '    <loc>'.$this->xml($this->settings->canonicalUrl('/sitemaps/pages-'.$sitemapNumber.'.xml')).'</loc>';
            $xml[] = '    <lastmod>'.now()->setTimestamp((int) $lastModified)->toAtomString().'</lastmod>';
            $xml[] = '  </sitemap>';
        }

        $xml[] = '</sitemapindex>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function sitemapPage(int $pageNumber): Response
    {
        $settings = $this->settings->publicPayload();

        if (! ($settings['seo_sitemap_enabled'] ?? true)) {
            abort(404);
        }

        $pages = $this->publishedPages()
            ->chunk(self::SITEMAP_CHUNK_SIZE)
            ->values()
            ->get($pageNumber - 1);

        if ($pages === null) {
            abort(404);
        }

        $changeFrequency = (string) ($settings['seo_sitemap_change_frequency'] ?? 'weekly');
        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($pages as $page) {
            $location = $this->settings->canonicalUrl($this->languages->pageRelativeUrl($page), null, (bool) $page->is_home);
            $lastModified = $page->updated_at ?? $page->published_at ?? now();
            $priority = $page->is_home ? '1.0' : '0.8';

            $xml[] = '  <url>';
            $xml[] = '    <loc>'.$this->xml($location).'</loc>';
            $xml[] = '    <lastmod>'.$lastModified->toAtomString().'</lastmod>';
            $xml[] = '    <changefreq>'.$this->xml($changeFrequency).'</changefreq>';
            $xml[] = '    <priority>'.$priority.'</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function excludedPaths(string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(fn (string $path): string => trim(trim($path), '/'))
            ->filter(fn (string $path): bool => $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function isExcluded(Page $page, array $excludedPaths): bool
    {
        if ($page->seo_noindex) {
            return true;
        }

        if ($excludedPaths === []) {
            return false;
        }

        $path = trim($page->path, '/');

        if ($page->is_home && in_array('', $excludedPaths, true)) {
            return true;
        }

        return in_array($path, $excludedPaths, true);
    }

    protected function publishedPages(): \Illuminate\Support\Collection
    {
        $settings = $this->settings->publicPayload();
        $excludedPaths = $this->excludedPaths((string) ($settings['seo_sitemap_excluded_paths'] ?? ''));

        return Page::query()
            ->with('language')
            ->published()
            ->where('visibility', PageVisibility::Public->value)
            ->whereIn('language_id', $this->languages->active()->pluck('id')->all())
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('is_home')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->reject(fn (Page $page): bool => $this->isExcluded($page, $excludedPaths))
            ->values();
    }
}