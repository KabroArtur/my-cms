<!doctype html>
@php
    $themePayload = app(\App\Core\Settings\Services\SettingsManager::class)->publicPayload();
    $defaultModeCandidate = $themePayload['admin_theme_mode'] ?? 'dark';
    $defaultLightPaletteCandidate = $themePayload['admin_light_palette'] ?? 'slate';
    $defaultDarkPaletteCandidate = $themePayload['admin_dark_palette'] ?? 'midnight';

    $defaultMode = in_array($defaultModeCandidate, ['light', 'dark'], true)
        ? $defaultModeCandidate
        : 'dark';
    $defaultLightPalette = in_array($defaultLightPaletteCandidate, ['slate', 'sand', 'forest'], true)
        ? $defaultLightPaletteCandidate
        : 'slate';
    $defaultDarkPalette = in_array($defaultDarkPaletteCandidate, ['midnight', 'ocean', 'graphite'], true)
        ? $defaultDarkPaletteCandidate
        : 'midnight';
    $defaultPalette = $defaultMode === 'dark' ? $defaultDarkPalette : $defaultLightPalette;
@endphp
<html lang="en" data-admin-theme-mode="{{ $defaultMode }}" data-admin-theme-palette="{{ $defaultPalette }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cms-admin-base" content="{{ app(\App\Core\Security\Services\AdminPathManager::class)->basePath() }}">
    <title>CMS Admin</title>
    @vite(['resources/css/app.scss', 'resources/js/admin/app.js'])
</head>
<body>
    <div id="admin-app"></div>
</body>
</html>