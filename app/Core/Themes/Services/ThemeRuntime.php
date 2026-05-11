<?php

namespace App\Core\Themes\Services;

use App\Core\Media\Models\MediaFile;
use App\Core\Pages\Enums\PageVisibility;
use App\Core\Pages\Contracts\PageRepository;
use App\Core\Pages\Models\Page;
use App\Core\Pages\Services\AdditionalFieldsService;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Themes\Data\ThemeDataBag;
use App\Core\Themes\Services\ThemeAssetManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class ThemeRuntime
{
    protected ?Page $currentPage = null;

    protected array $menuTreeCache = [];

    protected array $mediaByIdCache = [];

    protected array $mediaByNameCache = [];

    public function __construct(
        protected PageRepository $pages,
        protected SettingsManager $settings,
        protected ThemeAssetManager $assets,
        protected AdditionalFieldsService $additionalFields,
    ) {
    }

    public function usePage(?Page $page): self
    {
        $this->currentPage = $page;
        $this->menuTreeCache = [];

        return $this;
    }

    public function currentPage(): ?Page
    {
        return $this->currentPage;
    }

    public function siteName(): string
    {
        return (string) $this->setting('site_name', 'My CMS');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->publicPayload()[$key] ?? $default;
    }

    public function hasSetting(string $key): bool
    {
        return $this->valueExists($this->setting($key));
    }

    public function id(?Page $page = null): ?int
    {
        return $this->resolvePage($page)?->id;
    }

    public function title(?string $default = null, ?Page $page = null): string
    {
        return (string) ($this->resolvePage($page)?->title ?? $default ?? '');
    }

    public function content(mixed $default = null, ?Page $page = null): HtmlString
    {
        $page = $this->resolvePage($page);
        $content = $page?->content;

        if (is_string($content) && $content !== '') {
            return new HtmlString($content);
        }

        if ($default instanceof HtmlString) {
            return $default;
        }

        return new HtmlString((string) ($default ?? ''));
    }

    public function excerpt(string $default = '', ?Page $page = null): string
    {
        return (string) ($this->resolvePage($page)?->excerpt ?? $default);
    }

    public function slug(string $default = '', ?Page $page = null): string
    {
        return (string) ($this->resolvePage($page)?->slug ?? $default);
    }

    public function path(string $default = '', ?Page $page = null): string
    {
        $page = $this->resolvePage($page);

        if ($page === null) {
            return $default;
        }

        return $page->is_home ? '' : $page->path;
    }

    public function template(string $default = 'default', ?Page $page = null): string
    {
        return (string) ($this->resolvePage($page)?->template ?? $default);
    }

    public function status(string $default = '', ?Page $page = null): string
    {
        $status = $this->resolvePage($page)?->status;

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) ($status ?? $default);
    }

    public function date(?string $format = null, ?Page $page = null): string
    {
        $value = $this->resolvePage($page)?->published_at;

        return $value ? $value->format($format ?? $this->dateTimeFormat()) : '';
    }

    public function updatedDate(?string $format = null, ?Page $page = null): string
    {
        $value = $this->resolvePage($page)?->updated_at;

        return $value ? $value->format($format ?? $this->dateTimeFormat()) : '';
    }

    public function url(Page|array|string|null $target = null): string
    {
        if (is_string($target)) {
            return url(ltrim($target, '/'));
        }

        if ($target instanceof Page || is_array($target)) {
            return url($this->pageUrl($target));
        }

        $page = $this->resolvePage();

        return $page ? url($this->pageUrl($page)) : url('/');
    }

    public function homeUrl(): string
    {
        return url('/');
    }

    public function metaTitle(?Page $page = null): string
    {
        $page = $this->resolvePage($page);

        return (string) ($page?->meta_title ?: $page?->title ?: $this->siteName());
    }

    public function metaDescription(?Page $page = null): string
    {
        $page = $this->resolvePage($page);

        return (string) ($page?->meta_description ?: $page?->excerpt ?: '');
    }

    public function canonicalUrl(?Page $page = null): string
    {
        return $this->url($page);
    }

    public function robots(): string
    {
        return 'index,follow';
    }

    public function head(?Page $page = null): HtmlString
    {
        $parts = ['<title>'.e($this->metaTitle($page)).'</title>'];
        $description = $this->metaDescription($page);

        if ($description !== '') {
            $parts[] = '<meta name="description" content="'.e($description).'">';
        }

        $parts[] = '<link rel="canonical" href="'.e($this->canonicalUrl($page)).'">';
        $parts[] = '<meta name="robots" content="'.e($this->robots()).'">';

        if ($this->hasSetting('favicon_url')) {
            $parts[] = '<link rel="icon" href="'.e((string) $this->setting('favicon_url')).'">';
        }

        $styles = $this->assets->renderStyles($this->resolvePage($page));

        if ($styles !== '') {
            $parts[] = $styles;
        }
        $headScripts = $this->assets->renderScriptsInHead($this->resolvePage($page));

        if ($headScripts !== '') {
            $parts[] = $headScripts;
        }


        return new HtmlString(implode("\n", $parts));
    }

    public function field(string $key, mixed $default = null, ?Page $page = null): mixed
    {
        $value = $this->resolveFieldValue($key, $page);

        return $value ?? $default;
    }

    public function hasField(string $key, ?Page $page = null): bool
    {
        return $this->valueExists($this->resolveFieldValue($key, $page));
    }

    public function fieldRaw(string $key, mixed $default = null, ?Page $page = null): mixed
    {
        return $this->field($key, $default, $page);
    }

    public function fieldHtml(string $key, string $default = '', ?Page $page = null): HtmlString
    {
        return new HtmlString((string) $this->field($key, $default, $page));
    }

    public function fieldBool(string $key, bool $default = false, ?Page $page = null): bool
    {
        return filter_var($this->field($key, $default, $page), FILTER_VALIDATE_BOOL);
    }

    public function fieldNumber(string $key, int|float $default = 0, ?Page $page = null): int|float
    {
        $value = $this->field($key, $default, $page);

        return is_numeric($value) ? $value + 0 : $default;
    }

    public function fieldArray(string $key, ?Page $page = null): array
    {
        $value = $this->field($key, [], $page);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function customField(string $key, mixed $default = null, ?Page $page = null): mixed
    {
        return $this->field($key, $default, $page);
    }

    public function customFields(?Page $page = null): array
    {
        $page = $this->resolvePage($page);

        if ($page === null) {
            return [];
        }

        return $this->additionalFields->combinedValuesForPage($page);
    }

    public function group(string $key, ?Page $page = null): ThemeDataBag
    {
        $value = $this->fieldArray($key, $page);

        return new ThemeDataBag($this, Arr::isAssoc($value) ? $value : []);
    }

    public function repeater(string $key, ?Page $page = null): array
    {
        return array_values(array_map(
            fn (array $item): ThemeDataBag => new ThemeDataBag($this, $item),
            array_values(array_filter($this->fieldArray($key, $page), 'is_array')),
        ));
    }

    public function hasRepeater(string $key, ?Page $page = null): bool
    {
        return $this->repeater($key, $page) !== [];
    }

    /**
     * Возвращает меню как HTML по WordPress-подобному API.
     * Для обратной совместимости menu(Page $page) все еще отдаст дерево данных.
     */
    public function menu(string|Page|null $location = 'main', array $options = [], ?Page $currentPage = null): HtmlString|array
    {
        if ($location instanceof Page || $location === null) {
            return $this->menuTree($location);
        }

        $items = $this->menuTree($currentPage ?? $this->resolvePage());
        $options = array_replace([
            'container' => 'nav',
            'container_class' => '',
            'container_attrs' => [],
            'list' => true,
            'list_tag' => 'ul',
            'list_class' => '',
            'item_tag' => 'li',
            'item_class' => '',
            'active_class' => 'is-active',
            'ancestor_class' => 'is-ancestor',
            'link_class' => '',
            'link_attrs' => [],
            'children_tag' => 'ul',
            'children_class' => '',
            'depth' => null,
            'fallback' => false,
        ], $options);

        if ($items === [] && ! $options['fallback']) {
            return new HtmlString('');
        }

        $content = $options['list']
            ? $this->wrapTag(
                (string) $options['list_tag'],
                ['class' => $options['list_class'] ?: null],
                $this->renderMenuItems($items, $options),
            )
            : $this->renderMenuItems($items, $options);

        if (! $this->valueExists($options['container'])) {
            return new HtmlString($content);
        }

        $containerAttrs = array_merge($options['container_attrs'], [
            'class' => $this->classList([$options['container_class'] ?? null, $options['container_attrs']['class'] ?? null]),
        ]);

        return new HtmlString($this->wrapTag((string) $options['container'], $containerAttrs, $content));
    }

    public function breadcrumbsData(?Page $page = null): array
    {
        $page = $this->resolvePage($page);

        if ($page === null) {
            return [];
        }

        $trail = [];
        $tree = $this->menuTree($page);
        $this->collectBreadcrumbs($tree, $page->id, $trail);

        return $trail;
    }

    public function breadcrumbs(array $options = [], ?Page $page = null): HtmlString
    {
        $items = $this->breadcrumbsData($page);
        $options = array_replace([
            'container' => 'nav',
            'container_class' => 'breadcrumbs',
            'container_attrs' => [],
            'list_tag' => 'ol',
            'list_class' => 'breadcrumbs__list',
            'item_tag' => 'li',
            'item_class' => 'breadcrumbs__item',
            'link_class' => 'breadcrumbs__link',
            'current_class' => 'is-current',
        ], $options);

        if ($items === []) {
            return new HtmlString('');
        }

        $list = '';

        foreach ($items as $item) {
            $isCurrent = ! empty($item['is_current']);
            $classes = $this->classList([
                $options['item_class'],
                $isCurrent ? $options['current_class'] : null,
            ]);

            $label = e((string) ($item['title'] ?? ''));
            $content = $isCurrent
                ? $label
                : '<a '.$this->formatAttributes(['href' => $item['url'] ?? '#', 'class' => $options['link_class']]).'>'.$label.'</a>';

            $list .= $this->wrapTag((string) $options['item_tag'], ['class' => $classes ?: null], $content);
        }

        $content = $this->wrapTag((string) $options['list_tag'], ['class' => $options['list_class'] ?: null], $list);
        $containerAttrs = array_merge($options['container_attrs'], [
            'class' => $this->classList([$options['container_class'], $options['container_attrs']['class'] ?? null]),
        ]);

        return new HtmlString($this->wrapTag((string) $options['container'], $containerAttrs, $content));
    }

    /**
     * Возвращает меню сайта в простом виде для темы.
     * У каждого пункта есть title, url, children, is_current и is_ancestor.
     */
    public function menuData(?Page $currentPage = null): array
    {
        return $this->menuTree($currentPage);
    }

    public function menuTree(?Page $currentPage = null): array
    {
        $currentPage = $this->resolvePage($currentPage);
        $cacheKey = (string) ($currentPage?->id ?? 0);

        if (array_key_exists($cacheKey, $this->menuTreeCache)) {
            return $this->menuTreeCache[$cacheKey];
        }

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

        $this->menuTreeCache[$cacheKey] = array_map(fn (array $item): array => $this->stripParentId($item), $tree);

        return $this->menuTreeCache[$cacheKey];
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

    public function pageUrl(Page|array $page): string
    {
        if (is_array($page)) {
            return ($page['is_home'] ?? false) ? '/' : '/'.ltrim((string) ($page['path'] ?? ''), '/');
        }

        return $page->is_home ? '/' : '/'.$page->path;
    }

    public function hasImage(string $key = 'featured_image', ?Page $page = null): bool
    {
        return $this->media($key, $page) !== null;
    }

    public function imageUrl(string $key = 'featured_image', string $size = 'original', ?Page $page = null): ?string
    {
        return $this->imageUrlFromValue($this->media($key, $page), $size);
    }

    public function imageAlt(string $key = 'featured_image', string $default = '', ?Page $page = null): string
    {
        return $this->imageAltFromValue($this->media($key, $page), $default);
    }

    public function image(string $key = 'featured_image', array $options = [], ?Page $page = null): HtmlString
    {
        return $this->imageFromValue($this->media($key, $page), $options);
    }

    public function picture(string $key = 'featured_image', array $options = [], ?Page $page = null): HtmlString
    {
        $media = $this->media($key, $page);

        if ($media === null) {
            return new HtmlString('');
        }

        $pictureAttrs = Arr::except($options, ['size', 'loading', 'decoding', 'alt']);
        $content = $this->imageFromValue($media, $options)->toHtml();

        return new HtmlString($this->wrapTag('picture', $pictureAttrs, $content));
    }

    public function settingImage(string $key, array $options = []): HtmlString
    {
        return $this->imageFromValue($this->setting($key), $options);
    }

    public function mediaFile(string $name): ?MediaFile
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $cacheKey = mb_strtolower($name);

        if (array_key_exists($cacheKey, $this->mediaByNameCache)) {
            return $this->mediaByNameCache[$cacheKey];
        }

        $normalizedPath = trim(str_replace('\\', '/', $name), '/');

        if (str_contains($normalizedPath, '/')) {
            $pathMatch = MediaFile::query()
                ->whereRaw('LOWER(path) = ?', [mb_strtolower($normalizedPath)])
                ->orWhere(function ($query) use ($normalizedPath): void {
                    $query->whereRaw('LOWER(directory) = ?', [mb_strtolower(pathinfo($normalizedPath, PATHINFO_DIRNAME) === '.' ? '' : pathinfo($normalizedPath, PATHINFO_DIRNAME))])
                        ->whereRaw('LOWER(filename) = ?', [mb_strtolower(pathinfo($normalizedPath, PATHINFO_BASENAME))]);
                })
                ->first();

            if ($pathMatch instanceof MediaFile) {
                return $this->mediaByNameCache[$cacheKey] = $pathMatch;
            }
        }

        $exactMatch = MediaFile::query()
            ->whereRaw('LOWER(original_name) = ?', [$cacheKey])
            ->orWhereRaw('LOWER(filename) = ?', [$cacheKey])
            ->orWhereRaw('LOWER(title) = ?', [$cacheKey])
            ->first();

        if ($exactMatch instanceof MediaFile) {
            return $this->mediaByNameCache[$cacheKey] = $exactMatch;
        }

        $stem = mb_strtolower(pathinfo($name, PATHINFO_FILENAME));

        if ($stem === '' || $stem === $cacheKey) {
            return $this->mediaByNameCache[$cacheKey] = null;
        }

        return $this->mediaByNameCache[$cacheKey] = MediaFile::query()
            ->whereRaw('LOWER(title) = ?', [$stem])
            ->orWhereRaw('LOWER(original_name) LIKE ?', [$stem.'.%'])
            ->orWhereRaw('LOWER(filename) LIKE ?', [$stem.'.%'])
            ->first();
    }

    public function mediaUrl(string $name, string $size = 'original'): ?string
    {
        $media = $this->mediaFile($name);

        if ($media === null) {
            return null;
        }

        return $size === 'original' ? $media->url() : ($media->variantUrl($size) ?? $media->url());
    }

    public function mediaImage(string $name, array $options = []): HtmlString
    {
        return $this->imageFromValue($this->mediaFile($name), $options);
    }

    public function mediaMeta(string $name): ThemeDataBag
    {
        $media = $this->mediaFile($name);

        if ($media === null) {
            return new ThemeDataBag($this, []);
        }

        return new ThemeDataBag($this, [
            'id' => $media->id,
            'original_name' => $media->original_name,
            'filename' => $media->filename,
            'title' => $media->title,
            'alt_text' => $media->alt_text,
            'alt' => (string) ($media->alt_text ?: $media->title ?: $media->original_name ?: ''),
            'caption' => $media->caption,
            'description' => $media->description,
            'extension' => $media->extension,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'size_human' => $this->humanReadableBytes((int) $media->size),
            'width' => $media->width,
            'height' => $media->height,
            'directory' => $media->directory,
            'path' => $media->path,
            'url' => $media->url(),
            'preview_url' => $media->variantUrl((string) config('media.preview_variant', 'thumb')) ?? $media->url(),
            'variants' => is_array($media->variants) ? $media->variants : [],
        ]);
    }

    public function mediaValue(string $name, string $key, mixed $default = null): mixed
    {
        return $this->mediaMeta($name)->field($key, $default);
    }

    public function fileUrl(string $key, ?Page $page = null): ?string
    {
        $media = $this->media($key, $page);

        return $media?->url();
    }

    public function isHome(?Page $page = null): bool
    {
        return (bool) $this->resolvePage($page)?->is_home;
    }

    public function isPage(string $slug, ?Page $page = null): bool
    {
        $page = $this->resolvePage($page);

        return $page !== null && ($page->slug === $slug || $page->path === trim($slug, '/'));
    }

    public function isTemplate(string $template, ?Page $page = null): bool
    {
        return $this->template('', $page) === $template;
    }

    public function not(mixed $value): bool
    {
        return ! (bool) $value;
    }

    public function any(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if ((bool) $value) {
                return true;
            }
        }

        return false;
    }

    public function all(mixed ...$values): bool
    {
        if ($values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (! (bool) $value) {
                return false;
            }
        }

        return true;
    }

    public function year(string $format = 'Y'): string
    {
        return now()->format($format);
    }

    public function now(?string $format = null): string
    {
        return now()->format($format ?? 'Y-m-d H:i:s');
    }

    public function time(string $format = 'H:i'): string
    {
        return now()->format($format);
    }

    public function month(string $format = 'm'): string
    {
        return now()->format($format);
    }

    public function day(string $format = 'd'): string
    {
        return now()->format($format);
    }

    public function hour(string $format = 'H'): string
    {
        return now()->format($format);
    }

    public function minute(string $format = 'i'): string
    {
        return now()->format($format);
    }

    public function second(string $format = 's'): string
    {
        return now()->format($format);
    }

    public function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    public function lang(): string
    {
        return app()->getLocale();
    }

    public function languages(): array
    {
        return [[
            'code' => app()->getLocale(),
            'url' => url()->current(),
            'active' => true,
        ]];
    }

    public function translate(string $key, mixed $default = null, array $replace = []): string
    {
        $translated = __($key, $replace);

        return $translated === $key && $default !== null
            ? (string) $default
            : (string) $translated;
    }

    public function asset(string $path): string
    {
        return asset('themes/'.$this->settings->activeTheme().'/'.ltrim($path, '/'));
    }

    public function component(string $name, array $data = []): HtmlString
    {
        $componentPath = $this->resolveThemeComponentPath($name);

        if ($componentPath === null) {
            return new HtmlString('');
        }

        $payload = array_merge([
            'cms' => $this,
            'page' => $this->resolvePage(),
            'settings' => $this->settings->publicPayload(),
        ], $data);

        return new HtmlString((string) view()->file($componentPath, $payload)->render());
    }

    public function partial(string $name, array $data = []): HtmlString
    {
        return $this->component($name, $data);
    }

    public function templateContent(?string $template = null, ?Page $page = null): HtmlString
    {
        $page = $this->resolvePage($page);
        $template = trim((string) ($template ?? $page?->template ?? 'default'));

        if ($template === '' || $template === 'default' || $template === 'index.blade.php') {
            return new HtmlString('');
        }

        $entryPath = $this->settings->themeViewPath();
        $templatePath = $this->settings->themeViewPath($template);

        if ($templatePath === $entryPath || ! is_file($templatePath)) {
            return new HtmlString('');
        }

        return new HtmlString((string) view()->file($templatePath, [
            'cms' => $this,
            'page' => $page,
            'settings' => $this->settings->publicPayload(),
        ])->render());
    }

    public function hasComponent(string $name): bool
    {
        return $this->resolveThemeComponentPath($name) !== null;
    }

    public function hasPartial(string $name): bool
    {
        return $this->hasComponent($name);
    }

    public function form(string $key, array $options = []): HtmlString
    {
        $attrs = $this->formatAttributes($options);

        return new HtmlString('<form '.$attrs.' data-form-key="'.e($key).'"></form>');
    }

    public function pages(array $options = []): array
    {
        $options = array_replace([
            'parent' => null,
            'template' => null,
            'status' => 'published',
            'limit' => null,
            'order_by' => 'sort_order',
            'order' => 'asc',
        ], $options);

        $query = Page::query()->with('parent');

        if ($options['status'] === 'published') {
            $query->where('status', 'published')->where('visibility', PageVisibility::Public->value);
        }

        if (is_numeric($options['parent'])) {
            $query->where('parent_id', (int) $options['parent']);
        }

        if (is_string($options['template']) && $options['template'] !== '') {
            $query->where('template', $options['template']);
        }

        $orderBy = in_array((string) $options['order_by'], ['sort_order', 'title', 'published_at', 'created_at'], true)
            ? (string) $options['order_by']
            : 'sort_order';
        $order = strtolower((string) $options['order']) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($orderBy, $order)->orderBy('title');

        if (is_numeric($options['limit']) && (int) $options['limit'] > 0) {
            $query->limit((int) $options['limit']);
        }

        return $query->get()->map(fn (Page $page): object => (object) [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->path,
            'url' => $this->pageUrl($page),
            'excerpt' => $page->excerpt,
            'template' => $page->template,
            'status' => $page->status?->value ?? $page->status,
            'published_at' => $page->published_at,
        ])->all();
    }

    public function posts(array $options = []): array
    {
        $options = array_replace([
            'limit' => 5,
            'category_slug' => null,
            'status' => 'published',
            'section_slug' => 'blog',
        ], $options);

        if (Schema::hasTable('records') && Schema::hasTable('record_types')) {
            return $this->postsFromRecordTables($options);
        }

        if (Schema::hasTable('blog_posts')) {
            return $this->postsFromBlogTables($options);
        }

        if (Schema::hasTable('theme_posts')) {
            return $this->postsFromThemeTables($options);
        }

        return [];
    }

    public function categories(): array
    {
        if (Schema::hasTable('record_categories') && Schema::hasTable('record_types')) {
            return $this->categoriesFromRecordTables();
        }

        if (Schema::hasTable('blog_categories')) {
            return $this->categoriesFromBlogTables();
        }

        if (Schema::hasTable('theme_post_categories')) {
            return $this->categoriesFromThemeTables();
        }

        return [];
    }

    public function footer(): HtmlString
    {
        return new HtmlString($this->assets->renderScriptsInFooter($this->resolvePage()));
    }

    public function bodyClass(array|string|null $extra = null, ?Page $page = null): string
    {
        $page = $this->resolvePage($page);
        $classes = ['page'];

        if ($page !== null) {
            $classes[] = $page->is_home ? 'page-home' : 'page-'.$page->slug;
            $classes[] = 'template-'.($page->template ?: 'default');
        }

        return $this->classList(array_merge($classes, is_array($extra) ? $extra : [$extra]));
    }

    public function bodyAttrs(array $extra = [], ?Page $page = null): HtmlString
    {
        $page = $this->resolvePage($page);
        $attrs = array_merge([
            'class' => $this->bodyClass($extra['class'] ?? null, $page),
            'data-page-id' => $page?->id,
            'data-page-slug' => $page?->slug,
            'data-template' => $page?->template,
        ], Arr::except($extra, ['class']));

        return new HtmlString($this->formatAttributes($attrs));
    }

    public function attr(array $attrs): HtmlString
    {
        return new HtmlString($this->formatAttributes($attrs));
    }

    public function classList(array|string|null $classes): string
    {
        if (is_string($classes)) {
            return trim($classes);
        }

        if (! is_array($classes)) {
            return '';
        }

        $result = [];

        foreach ($classes as $key => $value) {
            if (is_int($key) && is_string($value) && $value !== '') {
                $result[] = $value;
                continue;
            }

            if (! is_int($key) && $value) {
                $result[] = (string) $key;
            }
        }

        return implode(' ', array_values(array_unique(array_filter($result))));
    }

    public function media(string $key = 'featured_image', ?Page $page = null): ?MediaFile
    {
        $page = $this->resolvePage($page);

        if ($page !== null && in_array($key, ['featured_image', 'featured_media', 'featured_media_id'], true)) {
            return $page->featuredMedia;
        }

        if (str_starts_with($key, 'setting:')) {
            return $this->mediaFromValue($this->setting(substr($key, 8)));
        }

        return $this->mediaFromValue($this->resolveFieldValue($key, $page));
    }

    public function mediaFromValue(mixed $value): ?MediaFile
    {
        if ($value instanceof MediaFile) {
            return $value;
        }

        if (is_numeric($value)) {
            $mediaId = (int) $value;

            if (! array_key_exists($mediaId, $this->mediaByIdCache)) {
                $this->mediaByIdCache[$mediaId] = MediaFile::query()->find($mediaId);
            }

            return $this->mediaByIdCache[$mediaId];
        }

        if (is_array($value)) {
            $mediaId = $value['id'] ?? $value['value'] ?? null;

            if (is_numeric($mediaId)) {
                $mediaId = (int) $mediaId;

                if (! array_key_exists($mediaId, $this->mediaByIdCache)) {
                    $this->mediaByIdCache[$mediaId] = MediaFile::query()->find($mediaId);
                }

                return $this->mediaByIdCache[$mediaId];
            }
        }

        if (is_string($value) && $value !== '') {
            return null;
        }

        return null;
    }

    public function imageFromValue(mixed $value, array $options = []): HtmlString
    {
        $media = $this->mediaFromValue($value);

        if ($media === null) {
            return new HtmlString('');
        }

        $size = (string) ($options['size'] ?? 'original');
        $attributes = Arr::except($options, ['size']);
        $attributes = array_merge([
            'src' => $this->imageUrlFromValue($media, $size),
            'alt' => $attributes['alt'] ?? $this->imageAltFromValue($media),
            'loading' => $attributes['loading'] ?? 'lazy',
            'decoding' => $attributes['decoding'] ?? 'async',
            'width' => $attributes['width'] ?? $media->width,
            'height' => $attributes['height'] ?? $media->height,
        ], $attributes);

        return new HtmlString('<img '.$this->formatAttributes($attributes).'>');
    }

    public function imageUrlFromValue(mixed $value, string $size = 'original'): ?string
    {
        $media = $this->mediaFromValue($value);

        if ($media !== null) {
            return $size === 'original' ? $media->url() : ($media->variantUrl($size) ?? $media->url());
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value) && is_string($value['url'] ?? null) && $value['url'] !== '') {
            return $value['url'];
        }

        return null;
    }

    public function imageAltFromValue(mixed $value, string $default = ''): string
    {
        $media = $this->mediaFromValue($value);

        if ($media !== null) {
            return (string) ($media->alt_text ?: $media->title ?: $media->original_name ?: $default);
        }

        if (is_array($value)) {
            return (string) ($value['alt_text'] ?? $value['title'] ?? $value['original_name'] ?? $default);
        }

        return $default;
    }

    protected function humanReadableBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = $size;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, '.', ' ').' '.$units[$index];
    }

    public function valueExists(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
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
        $currentPage = $this->resolvePage($currentPage);

        if ($currentPage === null) {
            return false;
        }

        return $page->id === $currentPage->id;
    }

    protected function isAncestor(Page $page, ?Page $currentPage): bool
    {
        $currentPage = $this->resolvePage($currentPage);

        if ($currentPage === null || $page->id === $currentPage->id) {
            return false;
        }

        $path = trim($currentPage->path, '/');
        $candidate = trim($page->path, '/');

        return $candidate !== '' && str_starts_with($path, $candidate.'/');
    }

    protected function resolvePage(?Page $page = null): ?Page
    {
        return $page ?? $this->currentPage;
    }

    protected function resolveFieldValue(string $key, ?Page $page = null): mixed
    {
        $page = $this->resolvePage($page);

        if ($page === null) {
            return null;
        }

        $builtIn = [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->path,
            'content' => $page->content,
            'excerpt' => $page->excerpt,
            'template' => $page->template,
            'status' => $page->status?->value ?? $page->status,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'featured_image' => $page->featured_media_id,
            'featured_media' => $page->featured_media_id,
            'published_at' => $page->published_at,
            'updated_at' => $page->updated_at,
        ];

        if (array_key_exists($key, $builtIn)) {
            return $builtIn[$key];
        }

        if ($this->additionalFields->hasPageValue($page, $key)) {
            return $this->additionalFields->pageValue($page, $key);
        }

        return $page->getAttribute($key);
    }

    protected function renderMenuItems(array $items, array $options, int $level = 1): string
    {
        $markup = '';
        $maxDepth = $options['depth'];

        foreach ($items as $item) {
            $itemClasses = $this->classList([
                $options['item_class'],
                ! empty($item['is_current']) ? $options['active_class'] : null,
                ! empty($item['is_ancestor']) ? $options['ancestor_class'] : null,
            ]);

            $linkAttrs = array_merge($options['link_attrs'], [
                'href' => $item['url'] ?? '#',
                'class' => $this->classList([$options['link_class'], $options['link_attrs']['class'] ?? null]),
            ]);

            $childrenMarkup = '';
            $canRenderChildren = ($item['children'] ?? []) !== []
                && ($maxDepth === null || $level < (int) $maxDepth);

            if ($canRenderChildren) {
                $childrenMarkup = $this->wrapTag(
                    (string) $options['children_tag'],
                    ['class' => $options['children_class'] ?: null],
                    $this->renderMenuItems($item['children'], $options, $level + 1),
                );
            }

            $itemContent = $this->wrapTag(
                'a',
                $linkAttrs,
                e((string) ($item['title'] ?? '')),
            ).$childrenMarkup;

            $markup .= $this->wrapTag((string) $options['item_tag'], ['class' => $itemClasses ?: null], $itemContent);
        }

        return $markup;
    }

    protected function wrapTag(string $tag, array $attributes, string $content): string
    {
        return '<'.$tag.($this->formatAttributes($attributes) !== '' ? ' '.$this->formatAttributes($attributes) : '').'>'.$content.'</'.$tag.'>';
    }

    protected function formatAttributes(array $attributes): string
    {
        $pairs = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if ($value === true) {
                $pairs[] = e((string) $name);
                continue;
            }

            $pairs[] = e((string) $name).'="'.e((string) $value).'"';
        }

        return implode(' ', $pairs);
    }

    protected function dateTimeFormat(): string
    {
        return trim((string) $this->setting('date_format', 'd.m.Y').' '.(string) $this->setting('time_format', 'H:i'));
    }

    protected function resolveThemeComponentPath(string $name): ?string
    {
        $relative = str_replace('.', '/', trim($name));
        $relative = ltrim($relative, '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        if (! str_ends_with($relative, '.blade.php')) {
            $relative .= '.blade.php';
        }

        $candidates = [
            $relative,
            'components/'.$relative,
            'partials/'.$relative,
        ];

        $themeRoot = base_path('themes/'.$this->settings->activeTheme());

        foreach (array_unique($candidates) as $candidate) {
            $filePath = $themeRoot.'/'.ltrim($candidate, '/');

            if (File::exists($filePath)) {
                return $filePath;
            }
        }

        return null;
    }

    protected function postsFromThemeTables(array $options): array
    {
        $query = DB::table('theme_posts as posts')
            ->leftJoin('theme_post_categories as categories', 'categories.id', '=', 'posts.category_id')
            ->select([
                'posts.id',
                'posts.title',
                'posts.slug',
                'posts.excerpt',
                'posts.content',
                'posts.is_published',
                'posts.published_at',
                'categories.name as category_name',
                'categories.slug as category_slug',
            ]);

        if (($options['status'] ?? 'published') === 'published') {
            $query->where('posts.is_published', true);
        }

        if (is_string($options['category_slug'] ?? null) && trim((string) $options['category_slug']) !== '') {
            $query->where('categories.slug', trim((string) $options['category_slug']));
        }

        $limit = max(1, (int) ($options['limit'] ?? 5));

        return $query
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.id')
            ->limit($limit)
            ->get()
            ->map(fn (object $post): object => (object) [
                'id' => $post->id,
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'excerpt' => (string) ($post->excerpt ?? ''),
                'content' => (string) ($post->content ?? ''),
                'published_at' => $post->published_at,
                'url' => '/posts/'.ltrim((string) $post->slug, '/'),
                'category' => $post->category_slug !== null
                    ? (object) [
                        'name' => (string) $post->category_name,
                        'slug' => (string) $post->category_slug,
                        'url' => '/posts/category/'.ltrim((string) $post->category_slug, '/'),
                    ]
                    : null,
            ])
            ->all();
    }

    protected function postsFromRecordTables(array $options): array
    {
        $sectionSlug = trim((string) ($options['section_slug'] ?? 'blog'));

        $section = DB::table('record_types')
            ->where('slug', $sectionSlug)
            ->where('status', 'active')
            ->first(['id', 'slug', 'name']);

        if ($section === null) {
            $section = DB::table('record_types')
                ->where('status', 'active')
                ->orderBy('name')
                ->first(['id', 'slug', 'name']);
        }

        if ($section === null) {
            return [];
        }

        $query = DB::table('records as posts')
            ->leftJoin('record_categories as categories', 'categories.id', '=', 'posts.category_id')
            ->select([
                'posts.id',
                'posts.title',
                'posts.slug',
                'posts.excerpt',
                'posts.content',
                'posts.status',
                'posts.published_at',
                'categories.name as category_name',
                'categories.slug as category_slug',
            ])
            ->where('posts.record_type_id', (int) $section->id);

        if (($options['status'] ?? 'published') === 'published') {
            $query->where('posts.status', 'published');
        }

        if (is_string($options['category_slug'] ?? null) && trim((string) $options['category_slug']) !== '') {
            $query->where('categories.slug', trim((string) $options['category_slug']));
        }

        $limit = max(1, (int) ($options['limit'] ?? 5));
        $sectionPath = '/'.ltrim((string) $section->slug, '/');

        return $query
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.id')
            ->limit($limit)
            ->get()
            ->map(fn (object $post): object => (object) [
                'id' => $post->id,
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'excerpt' => (string) ($post->excerpt ?? ''),
                'content' => (string) ($post->content ?? ''),
                'published_at' => $post->published_at,
                'url' => $sectionPath.'/'.ltrim((string) $post->slug, '/'),
                'category' => $post->category_slug !== null
                    ? (object) [
                        'name' => (string) $post->category_name,
                        'slug' => (string) $post->category_slug,
                        'url' => $sectionPath.'/category/'.ltrim((string) $post->category_slug, '/'),
                    ]
                    : null,
            ])
            ->all();
    }

    protected function postsFromBlogTables(array $options): array
    {
        $query = DB::table('blog_posts as posts')
            ->leftJoin('blog_categories as categories', 'categories.id', '=', 'posts.category_id')
            ->select([
                'posts.id',
                'posts.title',
                'posts.slug',
                'posts.excerpt',
                'posts.content',
                'posts.is_published',
                'posts.published_at',
                'categories.name as category_name',
                'categories.slug as category_slug',
            ]);

        if (($options['status'] ?? 'published') === 'published') {
            $query->where('posts.is_published', true);
        }

        if (is_string($options['category_slug'] ?? null) && trim((string) $options['category_slug']) !== '') {
            $query->where('categories.slug', trim((string) $options['category_slug']));
        }

        $limit = max(1, (int) ($options['limit'] ?? 5));

        return $query
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.id')
            ->limit($limit)
            ->get()
            ->map(fn (object $post): object => (object) [
                'id' => $post->id,
                'title' => (string) $post->title,
                'slug' => (string) $post->slug,
                'excerpt' => (string) ($post->excerpt ?? ''),
                'content' => (string) ($post->content ?? ''),
                'published_at' => $post->published_at,
                'url' => '/blog/'.ltrim((string) $post->slug, '/'),
                'category' => $post->category_slug !== null
                    ? (object) [
                        'name' => (string) $post->category_name,
                        'slug' => (string) $post->category_slug,
                        'url' => '/category/'.ltrim((string) $post->category_slug, '/'),
                    ]
                    : null,
            ])
            ->all();
    }

    protected function categoriesFromThemeTables(): array
    {
        return DB::table('theme_post_categories as categories')
            ->leftJoin('theme_posts as posts', 'posts.category_id', '=', 'categories.id')
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(posts.id) as posts_count'),
            ])
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (object $category): object => (object) [
                'id' => $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'url' => '/posts/category/'.ltrim((string) $category->slug, '/'),
                'posts_count' => (int) $category->posts_count,
            ])
            ->all();
    }

    protected function categoriesFromRecordTables(): array
    {
        $section = DB::table('record_types')
            ->where('status', 'active')
            ->where('slug', 'blog')
            ->first(['id', 'slug']);

        if ($section === null) {
            $section = DB::table('record_types')
                ->where('status', 'active')
                ->orderBy('name')
                ->first(['id', 'slug']);
        }

        if ($section === null) {
            return [];
        }

        $sectionPath = '/'.ltrim((string) $section->slug, '/');

        return DB::table('record_categories as categories')
            ->leftJoin('records as posts', 'posts.category_id', '=', 'categories.id')
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(posts.id) as posts_count'),
            ])
            ->where('categories.record_type_id', (int) $section->id)
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (object $category): object => (object) [
                'id' => $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'url' => $sectionPath.'/category/'.ltrim((string) $category->slug, '/'),
                'posts_count' => (int) $category->posts_count,
            ])
            ->all();
    }

    protected function categoriesFromBlogTables(): array
    {
        return DB::table('blog_categories as categories')
            ->leftJoin('blog_posts as posts', 'posts.category_id', '=', 'categories.id')
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(posts.id) as posts_count'),
            ])
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (object $category): object => (object) [
                'id' => $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'url' => '/category/'.ltrim((string) $category->slug, '/'),
                'posts_count' => (int) $category->posts_count,
            ])
            ->all();
    }
}