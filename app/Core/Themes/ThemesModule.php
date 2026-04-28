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
        Blade::directive('cmsSiteName', fn (?string $expression): string => "<?php echo e(app('".addslashes(ThemeRuntime::class)."')->siteName()); ?>");
        Blade::directive('cmsSetting', fn (?string $expression): string => "<?php echo e(app('".addslashes(ThemeRuntime::class)."')->setting(".($expression ?: "''").")); ?>");
        Blade::directive('cmsMenu', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'menuTree', 'cmsMenu'));
        Blade::directive('cmsBreadcrumbs', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'breadcrumbs', 'cmsBreadcrumbs'));
        Blade::directive('cmsChildren', fn (?string $expression): string => $this->compileAssignmentDirective($expression, 'children', 'cmsChildren'));
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

        return "<?php {$variable} = app('".addslashes(ThemeRuntime::class)."')->{$method}({$payload}); ?>";
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