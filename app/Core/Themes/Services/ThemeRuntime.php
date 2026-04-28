<?php

namespace App\Core\Themes\Services;

use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;

class ThemeRuntime
{
    public function __construct(
        protected PageRepository $pages,
        protected SettingsManager $settings,
    ) {
    }

    public function siteName(): string
    {
        return (string) $this->setting('site_name', 'My CMS');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->publicPayload()[$key] ?? $default;
    }

    /**
     * Возвращает меню сайта в простом виде для темы.
     * У каждого пункта есть title, url, children, is_current и is_ancestor.
     */
    public function menu(?Page $currentPage = null): array
    {
        return $this->menuTree($currentPage);
    }

    public function menuTree(?Page $currentPage = null): array
    {
        $pages = $this->pages->publicNavigation();
        $nodes = [];

        foreach ($pages as $page) {
            $nodes[$page->id] = [
                'id' => $page->id,
                'title' => $page->title,
                'url' => $this->pageUrl($page),
                'path' => $page->path,
                'is_home' => (bool) $page->is_home,
                'parent_id' => $page->parent_id,
                'depth' => 0,
                'is_current' => $this->isCurrent($page, $currentPage),
                'is_ancestor' => $this->isAncestor($page, $currentPage),
                'children' => [],
            ];
        }

        $tree = [];

        foreach ($nodes as $id => $node) {
            $parentId = $node['parent_id'];

            if ($parentId !== null && isset($nodes[$parentId])) {
                $nodes[$id]['depth'] = ($nodes[$parentId]['depth'] ?? 0) + 1;
                $nodes[$parentId]['children'][] = &$nodes[$id];
                continue;
            }

            $tree[] = &$nodes[$id];
        }

        return array_map(fn (array $item): array => $this->stripParentId($item), $tree);
    }

    public function children(Page|array|null $page, ?Page $currentPage = null): array
    {
        if ($page === null) {
            return [];
        }

        $node = is_array($page)
            ? $page
            : $this->findNodeById($this->menuTree($currentPage), $page->id);

        return is_array($node) ? ($node['children'] ?? []) : [];
    }

    public function breadcrumbs(?Page $page): array
    {
        if ($page === null) {
            return [];
        }

        $trail = [];
        $tree = $this->menuTree($page);
        $this->collectBreadcrumbs($tree, $page->id, $trail);

        return $trail;
    }

    public function pageUrl(Page|array $page): string
    {
        if (is_array($page)) {
            return ($page['is_home'] ?? false) ? '/' : '/'.ltrim((string) ($page['path'] ?? ''), '/');
        }

        return $page->is_home ? '/' : '/'.$page->path;
    }

    protected function stripParentId(array $item): array
    {
        unset($item['parent_id']);

        $item['children'] = array_map(fn (array $child): array => $this->stripParentId($child), $item['children'] ?? []);

        return $item;
    }

    protected function findNodeById(array $nodes, int $pageId): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $pageId) {
                return $node;
            }

            $found = $this->findNodeById($node['children'] ?? [], $pageId);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    protected function collectBreadcrumbs(array $nodes, int $pageId, array &$trail): bool
    {
        foreach ($nodes as $node) {
            $trail[] = $node;

            if (($node['id'] ?? null) === $pageId) {
                return true;
            }

            if ($this->collectBreadcrumbs($node['children'] ?? [], $pageId, $trail)) {
                return true;
            }

            array_pop($trail);
        }

        return false;
    }

    protected function isCurrent(Page $page, ?Page $currentPage): bool
    {
        if ($currentPage === null) {
            return false;
        }

        return $page->id === $currentPage->id;
    }

    protected function isAncestor(Page $page, ?Page $currentPage): bool
    {
        if ($currentPage === null || $page->id === $currentPage->id) {
            return false;
        }

        $path = trim($currentPage->path, '/');
        $candidate = trim($page->path, '/');

        return $candidate !== '' && str_starts_with($path, $candidate.'/');
    }
}