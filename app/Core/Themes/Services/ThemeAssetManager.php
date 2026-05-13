<?php

namespace App\Core\Themes\Services;

use App\Core\Pages\Models\Page;
use App\Core\Settings\Services\SettingsManager;
use App\Core\Support\Services\CmsCacheService;
use Illuminate\Support\Facades\File;

class ThemeAssetManager
{
    protected array $manifestCache = [];

    protected array $sourceHashCache = [];

    public function __construct(
        protected SettingsManager $settings,
        protected CmsCacheService $cache,
    ) {
    }

    public function renderStyles(?Page $page = null): string
    {
        $assets = $this->resolveAssets('styles', $page);

        if ($assets === []) {
            return '';
        }

        $theme = $this->settings->activeTheme();
        $shouldMinify = $this->settingBool('theme_assets_minify_css', true);
        $shouldCombine = $this->shouldCombineAssets('css');
        $withHash = $this->settingBool('theme_assets_use_hash', true);

        if ($shouldCombine) {
            $bundle = $this->buildCombinedBundle($theme, 'css', $assets, $shouldMinify, $withHash);

            return '<link rel="stylesheet" href="'.e($this->withVersion($bundle['url'], $bundle['version'], $withHash)).'">';
        }

        $markup = [];

        foreach ($assets as $asset) {
            $built = $this->buildSingleAsset($theme, 'css', $asset, $shouldMinify, $withHash);
            $markup[] = '<link rel="stylesheet" href="'.e($this->withVersion($built['url'], $built['version'], $withHash)).'">';
        }

        return implode("\n", $markup);
    }

    public function renderScriptsInHead(?Page $page = null): string
    {
        return $this->renderScriptsByPlacement($page, true);
    }

    public function renderScriptsInFooter(?Page $page = null): string
    {
        return $this->renderScriptsByPlacement($page, false);
    }

    protected function renderScriptsByPlacement(?Page $page, bool $inHead): string
    {
        $assets = array_values(array_filter(
            $this->resolveAssets('scripts', $page),
            fn (array $asset): bool => (bool) ($asset['in_head'] ?? false) === $inHead,
        ));

        if ($assets === []) {
            return '';
        }

        $theme = $this->settings->activeTheme();
        $shouldMinify = $this->settingBool('theme_assets_minify_js', true);
        $shouldObfuscate = $this->settingBool('theme_assets_obfuscate_js', false);
        $obfuscationPreset = $this->settingString('theme_assets_obfuscation_preset', 'balanced');
        $shouldCombine = $this->shouldCombineAssets('js');
        $deferByDefault = $this->settingBool('theme_assets_defer_scripts', true);
        $withHash = $this->settingBool('theme_assets_use_hash', true);

        if ($shouldCombine) {
            $bundle = $this->buildCombinedBundle($theme, 'js', $assets, $shouldMinify, $withHash, $shouldObfuscate, $obfuscationPreset);
            $defer = $deferByDefault || collect($assets)->contains(fn (array $item): bool => (bool) ($item['defer'] ?? false));
            $async = collect($assets)->contains(fn (array $item): bool => (bool) ($item['async'] ?? false));

            return '<script src="'.e($this->withVersion($bundle['url'], $bundle['version'], $withHash)).'"'.$this->scriptAttribute($defer, 'defer').$this->scriptAttribute($async, 'async').'></script>';
        }

        $markup = [];

        foreach ($assets as $asset) {
            $built = $this->buildSingleAsset($theme, 'js', $asset, $shouldMinify, $withHash, $shouldObfuscate, $obfuscationPreset);
            $defer = $deferByDefault || (bool) ($asset['defer'] ?? false);
            $async = (bool) ($asset['async'] ?? false);

            $markup[] = '<script src="'.e($this->withVersion($built['url'], $built['version'], $withHash)).'"'.$this->scriptAttribute($defer, 'defer').$this->scriptAttribute($async, 'async').'></script>';
        }

        return implode("\n", $markup);
    }

    protected function resolveAssets(string $type, ?Page $page): array
    {
        $theme = $this->settings->activeTheme();
        $manifest = $this->themeManifest($theme);
        $entries = $manifest['assets'][$type] ?? [];

        if (! is_array($entries)) {
            return [];
        }

        $tokens = $this->templateTokens($page);
        $assets = [];

        foreach (array_values($entries) as $index => $entry) {
            if (! is_array($entry) || ! isset($entry['src']) || ! is_string($entry['src'])) {
                continue;
            }

            if (! $this->assetIsApplicable($entry, $tokens)) {
                continue;
            }

            $relativeSource = $this->normalizeSourcePath($entry['src']);
            $absolutePath = base_path('themes/'.$theme.'/'.$relativeSource);

            if (! is_file($absolutePath)) {
                continue;
            }

            $handle = (string) ($entry['handle'] ?? pathinfo($relativeSource, PATHINFO_FILENAME));

            if ($handle === '') {
                continue;
            }

            $assets[] = [
                'handle' => $handle,
                'source_relative' => $relativeSource,
                'source_absolute' => $absolutePath,
                'defer' => (bool) ($entry['defer'] ?? false),
                'async' => (bool) ($entry['async'] ?? false),
                'in_head' => (bool) ($entry['in_head'] ?? false),
                'priority' => is_numeric($entry['priority'] ?? null) ? (int) $entry['priority'] : 10,
                'deps' => $this->normalizeDeps($entry['deps'] ?? []),
                'order' => $index,
            ];
        }

        return $this->orderAssets($assets);
    }

    protected function normalizeDeps(mixed $deps): array
    {
        if (! is_array($deps)) {
            return [];
        }

        $normalized = [];

        foreach ($deps as $dep) {
            if (! is_string($dep)) {
                continue;
            }

            $dep = trim($dep);

            if ($dep !== '') {
                $normalized[] = $dep;
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function orderAssets(array $assets): array
    {
        if ($assets === []) {
            return [];
        }

        $map = [];

        foreach ($assets as $asset) {
            $map[$asset['handle']] = $asset;
        }

        $inDegree = [];
        $adjacency = [];

        foreach ($map as $handle => $asset) {
            $inDegree[$handle] = $inDegree[$handle] ?? 0;

            foreach ($asset['deps'] as $depHandle) {
                if (! isset($map[$depHandle])) {
                    continue;
                }

                $adjacency[$depHandle] = $adjacency[$depHandle] ?? [];
                $adjacency[$depHandle][] = $handle;
                $inDegree[$handle] = ($inDegree[$handle] ?? 0) + 1;
            }
        }

        $queue = [];

        foreach ($map as $handle => $asset) {
            if (($inDegree[$handle] ?? 0) === 0) {
                $queue[] = $asset;
            }
        }

        $this->sortByWeight($queue);

        $sorted = [];
        $visited = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === null) {
                break;
            }

            $handle = $current['handle'];

            if (isset($visited[$handle])) {
                continue;
            }

            $visited[$handle] = true;
            $sorted[] = $current;

            foreach ($adjacency[$handle] ?? [] as $childHandle) {
                $inDegree[$childHandle] = max(0, ($inDegree[$childHandle] ?? 0) - 1);

                if ($inDegree[$childHandle] === 0 && isset($map[$childHandle])) {
                    $queue[] = $map[$childHandle];
                }
            }

            $this->sortByWeight($queue);
        }

        if (count($sorted) === count($map)) {
            return $sorted;
        }

        $remaining = [];

        foreach ($map as $handle => $asset) {
            if (! isset($visited[$handle])) {
                $remaining[] = $asset;
            }
        }

        $this->sortByWeight($remaining);

        return array_merge($sorted, $remaining);
    }

    protected function sortByWeight(array &$assets): void
    {
        usort($assets, function (array $left, array $right): int {
            $priorityCompare = ($left['priority'] ?? 10) <=> ($right['priority'] ?? 10);

            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return ($left['order'] ?? 0) <=> ($right['order'] ?? 0);
        });
    }

    protected function assetIsApplicable(array $entry, array $tokens): bool
    {
        if (($entry['global'] ?? false) === true) {
            return true;
        }

        $templates = $entry['templates'] ?? null;

        if (! is_array($templates) || $templates === []) {
            return false;
        }

        foreach ($templates as $template) {
            if (! is_string($template)) {
                continue;
            }

            if (in_array(strtolower(trim($template)), $tokens, true)) {
                return true;
            }
        }

        return false;
    }

    protected function templateTokens(?Page $page): array
    {
        if ($page === null) {
            return ['global'];
        }

        $tokens = [
            strtolower((string) ($page->template ?: 'default')),
            strtolower((string) $page->slug),
            strtolower((string) ($page->path ?: 'home')),
        ];

        if ((bool) $page->is_home) {
            $tokens[] = 'home';
        }

        $tokens[] = 'global';

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => $token !== '')));
    }

    protected function normalizeSourcePath(string $source): string
    {
        $source = ltrim(str_replace('\\', '/', trim($source)), '/');

        if ($source === '') {
            return '';
        }

        if (str_starts_with($source, 'assets/')) {
            return $source;
        }

        return 'assets/'.$source;
    }

    protected function buildCombinedBundle(string $theme, string $type, array $assets, bool $minify, bool $withHash, bool $obfuscate = false, string $obfuscationPreset = 'balanced'): array
    {
        $extension = $type === 'css' ? 'css' : 'js';
        $hashSeed = [$theme, $type, $minify ? '1' : '0', $obfuscate ? '1' : '0', $obfuscationPreset];
        $content = [];

        foreach ($assets as $asset) {
            $sourceHash = $this->sourceHash($asset['source_absolute']);
            $hashSeed[] = $asset['handle'].':'.$sourceHash;
            $content[] = File::get($asset['source_absolute']);
        }

        $hash = sha1(implode('|', $hashSeed));
        $compiled = implode("\n", $content);
        $compiled = $this->compileAssetContent($compiled, $type, $minify, $obfuscate, $obfuscationPreset);

        $directories = $this->compiledAssetDirectories($theme, $type);

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $baseName = $this->readableBundleName($assets);
        $fileName = $withHash
            ? 'bundle-'.$hash.'.'.$extension
            : $baseName.($minify ? '.min' : '').'.'.$extension;
        foreach ($directories as $directory) {
            $absoluteTarget = $directory.'/'.$fileName;

            if (! is_file($absoluteTarget) || (string) File::get($absoluteTarget) !== $compiled) {
                File::put($absoluteTarget, $compiled);
            }
        }

        return [
            'url' => asset('build/theme-assets/'.$theme.'/'.$type.'/'.$fileName),
            'version' => substr($hash, 0, 12),
        ];
    }

    protected function buildSingleAsset(string $theme, string $type, array $asset, bool $minify, bool $withHash, bool $obfuscate = false, string $obfuscationPreset = 'balanced'): array
    {
        $extension = $type === 'css' ? 'css' : 'js';
        $sourceHash = $this->sourceHash($asset['source_absolute']);
        $hash = sha1($type.'|'.$asset['handle'].'|'.$sourceHash.'|'.($minify ? '1' : '0').'|'.($obfuscate ? '1' : '0').'|'.$obfuscationPreset);
        $directories = $this->compiledAssetDirectories($theme, $type);

        foreach ($directories as $directory) {
            File::ensureDirectoryExists($directory);
        }

        $baseName = $this->readableAssetName((string) ($asset['handle'] ?? 'asset'));
        $fileName = $withHash
            ? $asset['handle'].'-'.$hash.'.'.$extension
            : $baseName.($minify ? '.min' : '').'.'.$extension;
        $compiled = $this->compileAssetContent((string) File::get($asset['source_absolute']), $type, $minify, $obfuscate, $obfuscationPreset);

        foreach ($directories as $directory) {
            $absoluteTarget = $directory.'/'.$fileName;

            if (! is_file($absoluteTarget) || (string) File::get($absoluteTarget) !== $compiled) {
                File::put($absoluteTarget, $compiled);
            }
        }

        return [
            'url' => asset('build/theme-assets/'.$theme.'/'.$type.'/'.$fileName),
            'version' => substr($hash, 0, 12),
        ];
    }

    protected function compiledAssetDirectories(string $theme, string $type): array
    {
        $relativePath = 'build/theme-assets/'.$theme.'/'.$type;

        $directories = [];

        if ($this->sharedHostingFlatPublicDisk()) {
            $directories[] = base_path($relativePath);
        }

        $directories[] = public_path($relativePath);

        return array_values(array_unique($directories));
    }

    protected function sharedHostingFlatPublicDisk(): bool
    {
        return (bool) config('cms.shared_hosting_flat_public_disk', false);
    }

    protected function shouldCombineAssets(string $type): bool
    {
        if ($this->sharedHostingFlatPublicDisk()) {
            return true;
        }

        return $this->settingBool($type === 'css' ? 'theme_assets_combine_css' : 'theme_assets_combine_js', true);
    }

    protected function minify(string $content, string $type): string
    {
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = preg_replace('/\s+/', ' ', $trimmed) ?: $trimmed;
        }

        $joined = implode($type === 'css' ? '' : "\n", $normalized);

        if ($type === 'css') {
            $joined = str_replace([' {', '{ ', ' }', '; ', ': ', ', ', ' > '], ['{', '{', '}', ';', ':', ',', '>'], $joined);
        }

        return trim($joined);
    }

    protected function compileAssetContent(string $content, string $type, bool $minify, bool $obfuscate, string $obfuscationPreset): string
    {
        if ($minify) {
            $content = $this->minify($content, $type);
        }

        if ($type === 'js' && $obfuscate) {
            $content = $this->obfuscateJavaScript($content, $obfuscationPreset);
        }

        return $content;
    }

    protected function obfuscateJavaScript(string $content, string $preset): string
    {
        if (trim($content) === '') {
            return '';
        }

        $binary = $this->javascriptObfuscatorBinary();

        if ($binary === null) {
            return $content;
        }

        $inputFile = tempnam(sys_get_temp_dir(), 'cms-js-src-');
        $outputFile = tempnam(sys_get_temp_dir(), 'cms-js-out-');
        $configFile = tempnam(sys_get_temp_dir(), 'cms-js-cfg-');

        if ($inputFile === false || $outputFile === false || $configFile === false) {
            foreach ([$inputFile, $outputFile, $configFile] as $path) {
                if (is_string($path) && $path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }

            return $content;
        }

        try {
            File::put($inputFile, $content);
            File::put($configFile, json_encode($this->javascriptObfuscatorOptions($preset), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $command = sprintf(
                '%s %s --output %s --config %s 2>&1',
                escapeshellarg($binary),
                escapeshellarg($inputFile),
                escapeshellarg($outputFile),
                escapeshellarg($configFile),
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || ! is_file($outputFile)) {
                return $content;
            }

            $obfuscated = (string) File::get($outputFile);

            return trim($obfuscated) !== '' ? $obfuscated : $content;
        } finally {
            @unlink($inputFile);
            @unlink($outputFile);
            @unlink($configFile);
        }
    }

    protected function javascriptObfuscatorBinary(): ?string
    {
        foreach ([
            base_path('node_modules/.bin/javascript-obfuscator'),
            base_path('node_modules/javascript-obfuscator/bin/javascript-obfuscator.js'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function javascriptObfuscatorOptions(string $preset): array
    {
        $preset = in_array($preset, ['safe', 'balanced', 'aggressive'], true) ? $preset : 'balanced';

        $base = [
            'compact' => true,
            'identifierNamesGenerator' => 'hexadecimal',
            'ignoreImports' => true,
            'numbersToExpressions' => true,
            'renameGlobals' => false,
            'renameProperties' => false,
            'simplify' => true,
            'splitStrings' => false,
            'stringArray' => true,
            'stringArrayCallsTransform' => true,
            'stringArrayEncoding' => ['base64'],
            'stringArrayIndexesType' => ['hexadecimal-number'],
            'stringArrayRotate' => true,
            'stringArrayShuffle' => true,
            'stringArrayWrappersType' => 'function',
            'target' => 'browser',
            'transformObjectKeys' => false,
            'unicodeEscapeSequence' => false,
        ];

        if ($preset === 'safe') {
            return $base + [
                'controlFlowFlattening' => false,
                'deadCodeInjection' => false,
                'splitStringsChunkLength' => 8,
                'stringArrayThreshold' => 0.8,
                'stringArrayWrappersCount' => 1,
            ];
        }

        if ($preset === 'aggressive') {
            return $base + [
                'controlFlowFlattening' => true,
                'controlFlowFlatteningThreshold' => 0.75,
                'deadCodeInjection' => true,
                'deadCodeInjectionThreshold' => 0.2,
                'splitStrings' => true,
                'splitStringsChunkLength' => 6,
                'stringArrayThreshold' => 1,
                'stringArrayWrappersCount' => 3,
                'transformObjectKeys' => true,
            ];
        }

        return $base + [
            'controlFlowFlattening' => true,
            'controlFlowFlatteningThreshold' => 0.35,
            'deadCodeInjection' => false,
            'splitStrings' => true,
            'splitStringsChunkLength' => 8,
            'stringArrayThreshold' => 1,
            'stringArrayWrappersCount' => 2,
            'transformObjectKeys' => true,
        ];
    }

    protected function sourceHash(string $absolutePath): string
    {
        if (isset($this->sourceHashCache[$absolutePath])) {
            return $this->sourceHashCache[$absolutePath];
        }

        $this->sourceHashCache[$absolutePath] = sha1((string) @filemtime($absolutePath).'|'.(string) @filesize($absolutePath));

        return $this->sourceHashCache[$absolutePath];
    }

    protected function readableBundleName(array $assets): string
    {
        $handles = collect($assets)
            ->map(fn (array $asset): string => (string) ($asset['handle'] ?? 'asset'))
            ->values()
            ->all();

        $joined = implode('-', $handles);
        $joined = strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $joined));
        $joined = trim($joined, '-');

        if ($joined === '') {
            $joined = 'bundle';
        }

        if (strlen($joined) > 80) {
            $joined = substr($joined, 0, 80);
            $joined = rtrim($joined, '-');
        }

        return $joined;
    }

    protected function readableAssetName(string $handle): string
    {
        $name = strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $handle));
        $name = trim($name, '-');

        if ($name === '') {
            $name = 'asset';
        }

        return $name;
    }

    protected function withVersion(string $url, string $version, bool $enabled): string
    {
        if (! $enabled || $version === '') {
            return $url;
        }

        return str_contains($url, '?') ? $url.'&v='.$version : $url.'?v='.$version;
    }

    protected function scriptAttribute(bool $enabled, string $name): string
    {
        return $enabled ? ' '.$name : '';
    }

    protected function settingBool(string $key, bool $default = false): bool
    {
        return filter_var($this->settings->publicPayload()[$key] ?? $default, FILTER_VALIDATE_BOOL);
    }

    protected function settingString(string $key, string $default = ''): string
    {
        $value = $this->settings->publicPayload()[$key] ?? $default;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    protected function themeManifest(string $theme): array
    {
        if (isset($this->manifestCache[$theme])) {
            return $this->manifestCache[$theme];
        }

        $metadataPath = base_path('themes/'.$theme.'/theme.json');

        if (! is_file($metadataPath)) {
            $this->manifestCache[$theme] = [];

            return $this->manifestCache[$theme];
        }

        $cacheKey = sprintf('theme-manifest:%s:%s', $theme, sha1((string) @filemtime($metadataPath)));

        $manifest = $this->cache->rememberSite(
            key: $cacheKey,
            resolver: function () use ($metadataPath): array {
                $decoded = json_decode((string) File::get($metadataPath), true);

                return is_array($decoded) ? $decoded : [];
            },
            ttlSeconds: 300,
        );

        $this->manifestCache[$theme] = is_array($manifest) ? $manifest : [];

        return $this->manifestCache[$theme];
    }
}
