<?php

namespace App\Core\Themes;

use App\Core\Themes\Services\ThemeRuntime;
use App\Core\Support\BaseCoreModule;
use App\Core\Support\ModuleDefinition;
use Illuminate\Support\Facades\Blade;

/**
 * Модуль тем станет слоем для управления внешним видом сайта.
 * Он позже объединит активную тему, шаблоны и тему по умолчанию.
 */
class ThemesModule extends BaseCoreModule
{
    public function register(): void
    {
        $this->app->singleton(ThemeRuntime::class, ThemeRuntime::class);
    }

    public function boot(): void
    {
        $this->registerPrefixedHelpers();
        $runtime = var_export(ThemeRuntime::class, true);
        Blade::directive('cmsSiteName', fn (?string $expression): string => "<?php echo e(app({$runtime})->siteName()); ?>");
        Blade::directive('cmsSetting', fn (?string $expression): string => "<?php echo e(app({$runtime})->setting(".($expression ?: "''").")); ?>");
        Blade::directive('cmsMenu', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'menuTree', 'cmsMenu'));
        Blade::directive('cmsBreadcrumbs', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'breadcrumbsData', 'cmsBreadcrumbs'));
        Blade::directive('cmsChildren', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'children', 'cmsChildren'));
    }

    protected function registerPrefixedHelpers(): void
    {
        $prefix = $this->normalizePrefix((string) config('cms.theme_macro_prefix', 'cms_'));
        $runtime = ThemeRuntime::class;
        $macros = [
            'site_name' => 'siteName',
            'setting' => 'setting',
            'has_setting' => 'hasSetting',
            'id' => 'id',
            'title' => 'title',
            'content' => 'content',
            'excerpt' => 'excerpt',
            'slug' => 'slug',
            'url' => 'url',
            'path' => 'path',
            'template' => 'template',
            'status' => 'status',
            'date' => 'date',
            'updated_date' => 'updatedDate',
            'field' => 'field',
            'has_field' => 'hasField',
            'field_raw' => 'fieldRaw',
            'field_html' => 'fieldHtml',
            'field_bool' => 'fieldBool',
            'field_number' => 'fieldNumber',
            'field_array' => 'fieldArray',
            'group' => 'group',
            'repeater' => 'repeater',
            'has_repeater' => 'hasRepeater',
            'menu' => 'menu',
            'breadcrumbs' => 'breadcrumbs',
            'image' => 'image',
            'image_url' => 'imageUrl',
            'image_alt' => 'imageAlt',
            'has_image' => 'hasImage',
            'picture' => 'picture',
            'media_file' => 'mediaFile',
            'media_url' => 'mediaUrl',
            'media_image' => 'mediaImage',
            'media_meta' => 'mediaMeta',
            'media_value' => 'mediaValue',
            'file_url' => 'fileUrl',
            'setting_image' => 'settingImage',
            'meta_title' => 'metaTitle',
            'meta_description' => 'metaDescription',
            'canonical_url' => 'canonicalUrl',
            'robots' => 'robots',
            'head' => 'head',
            'home_url' => 'homeUrl',
            'lang' => 'lang',
            'languages' => 'languages',
            'current_language' => 'currentLanguage',
            'current_language_code' => 'currentLanguageCode',
            'current_language_locale' => 'currentLanguageLocale',
            'default_language' => 'defaultLanguage',
            'active_languages' => 'activeLanguages',
            'languages_count' => 'languagesCount',
            'language_links' => 'languageLinks',
            'hreflang_tags' => 'hreflangTags',
            'html_lang' => 'htmlLang',
            'translate' => 'translate',
            't' => 'translate',
            'asset' => 'asset',
            'component' => 'component',
            'partial' => 'partial',
            'template_content' => 'templateContent',
            'has_component' => 'hasComponent',
            'has_partial' => 'hasPartial',
            'form' => 'form',
            'pages' => 'pages',
            'posts' => 'posts',
            'categories' => 'categories',
            'is_home' => 'isHome',
            'is_page' => 'isPage',
            'is_template' => 'isTemplate',
            'not' => 'not',
            'any' => 'any',
            'all' => 'all',
            'logic_not' => 'not',
            'logic_or' => 'any',
            'logic_and' => 'all',
            'now' => 'now',
            'time' => 'time',
            'year' => 'year',
            'month' => 'month',
            'day' => 'day',
            'hour' => 'hour',
            'minute' => 'minute',
            'second' => 'second',
            'timezone' => 'timezone',
            'footer' => 'footer',
            'body_attrs' => 'bodyAttrs',
            'body_class' => 'bodyClass',
            'attr' => 'attr',
            'class' => 'classList',
        ];

        foreach ($macros as $suffix => $method) {
            $function = $prefix.$suffix;

            if (function_exists($function)) {
                continue;
            }

            eval(sprintf(
                'namespace { function %1$s(...$arguments) { return app(%2$s)->%3$s(...$arguments); } }',
                $function,
                var_export($runtime, true),
                $method,
            ));
        }

        $this->registerAliasHelpers($runtime, $prefix, $macros);
    }

    protected function registerAliasHelpers(string $runtime, string $prefix, array $macros): void
    {
        $aliases = [];

        foreach ($macros as $suffix => $method) {
            $aliases[$suffix] = $method;
        }

        $fallbackAliases = [
            'url' => 'page_url',
            'asset' => 'theme_asset',
            'class' => 'class_list',
            'date' => 'page_date',
            'time' => 'date_time',
            'now' => 'date_now',
            'year' => 'date_year',
            'month' => 'date_month',
            'day' => 'date_day',
            'hour' => 'date_hour',
            'minute' => 'date_minute',
            'second' => 'date_second',
            'timezone' => 'date_timezone',
            'head' => 'theme_head',
            'footer' => 'theme_footer',
        ];

        foreach ($aliases as $function => $method) {
            if ($this->isAliasFunctionAllowed($function) && ! function_exists($function)) {
                eval(sprintf(
                    'namespace { function %1$s(...$arguments) { return app(%2$s)->%3$s(...$arguments); } }',
                    $function,
                    var_export($runtime, true),
                    $method,
                ));
            }

            $fallback = $fallbackAliases[$function] ?? null;

            if ($fallback === null || ! $this->isAliasFunctionAllowed($fallback) || function_exists($fallback)) {
                continue;
            }

            eval(sprintf(
                'namespace { function %1$s(...$arguments) { return app(%2$s)->%3$s(...$arguments); } }',
                $fallback,
                var_export($runtime, true),
                $method,
            ));
        }
    }

    protected function isAliasFunctionAllowed(string $name): bool
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            return false;
        }

        return ! in_array(strtolower($name), [
            'class',
            'function',
            'trait',
            'interface',
            'namespace',
            'use',
            'new',
            'static',
            'public',
            'private',
            'protected',
            'abstract',
            'final',
            'echo',
            'print',
            'match',
            'fn',
            'if',
            'else',
            'elseif',
            'switch',
            'case',
            'default',
            'while',
            'for',
            'foreach',
            'do',
            'try',
            'catch',
            'finally',
            'return',
            'yield',
            'array',
            'callable',
            'iterable',
            'object',
            'string',
            'int',
            'float',
            'bool',
            'null',
            'true',
            'false',
        ], true);
    }

    protected function normalizePrefix(string $prefix): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix)
            ? $prefix
            : 'cms_';
    }

    protected function compileAssignmentDirective(?string $expression, string $method, string $defaultVariable): string
    {
        $expression = trim((string) $expression);

        if ($expression === '') {
            return '';
        }

        [$target, $payload] = array_pad(explode(',', $expression, 2), 2, null);

        $target = trim((string) $target);
        $payload = trim((string) ($payload ?? ''));

        if ($target === '') {
            return '';
        }

        $variable = str_starts_with($target, '$')
            ? $target
            : '$'.trim($target, "'\" ");

        if ($variable === '$') {
            $variable = '$'.$defaultVariable;
        }

        $payload = $payload !== '' ? $payload : 'null';

        return "<?php {$variable} = app(".var_export(ThemeRuntime::class, true).")->{$method}({$payload}); ?>";
    }

    protected function newDefinition(): ModuleDefinition
    {
        return new ModuleDefinition(
            key: 'themes',
            name: 'Themes',
            description: 'Модуль отвечает за темы, шаблоны и активный frontend.',
        );
    }
}