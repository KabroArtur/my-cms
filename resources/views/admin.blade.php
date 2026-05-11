<!doctype html>
@php
    $themePayload = app(\App\Core\Settings\Services\SettingsManager::class)->publicPayload();
    $adminUser = auth()->user()?->loadMissing(['roles.permissions', 'permissions']);
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
    $adminBootstrap = [
        'siteName' => $themePayload['site_name'] ?? 'My CMS',
        'currentUser' => $adminUser
            ? \App\Http\Resources\Admin\AuthenticatedUserResource::make($adminUser)->resolve(request())
            : null,
    ];
@endphp
<html lang="en" data-admin-theme-mode="{{ $defaultMode }}" data-admin-theme-palette="{{ $defaultPalette }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cms-admin-base" content="{{ app(\App\Core\Security\Services\AdminPathManager::class)->basePath() }}">
    <meta name="cms-site-name" content="{{ e($adminBootstrap['siteName']) }}">
    <title>CMS Admin</title>
    <script nonce="{{ $cspNonce ?? request()->attributes->get('csp_nonce', '') }}">
        (() => {
            try {
                const raw = window.localStorage.getItem('cms-admin-theme')

                if (!raw) {
                    return
                }

                const theme = JSON.parse(raw)
                const root = document.documentElement
                const mode = typeof theme?.mode === 'string' ? theme.mode : null
                const palette = mode === 'dark'
                    ? theme?.darkPalette
                    : theme?.lightPalette

                if (mode === 'light' || mode === 'dark') {
                    root.setAttribute('data-admin-theme-mode', mode)
                }

                if (typeof palette === 'string' && palette !== '') {
                    root.setAttribute('data-admin-theme-palette', palette)
                }
            } catch (error) {
                // Ignore malformed local preference and keep server defaults.
            }
        })()
    </script>
    <style>
        html {
            background: #071223;
            color: #e7efff;
        }

        html[data-admin-theme-mode='light'][data-admin-theme-palette='slate'] {
            background: #f2f6ff;
            color: #102445;
        }

        html[data-admin-theme-mode='light'][data-admin-theme-palette='sand'] {
            background: #f6f2ea;
            color: #3f2f1f;
        }

        html[data-admin-theme-mode='light'][data-admin-theme-palette='forest'] {
            background: #edf5f1;
            color: #143227;
        }

        html[data-admin-theme-mode='dark'][data-admin-theme-palette='midnight'] {
            background: #071223;
            color: #e7efff;
        }

        html[data-admin-theme-mode='dark'][data-admin-theme-palette='ocean'] {
            background: #061a1f;
            color: #e0fbff;
        }

        html[data-admin-theme-mode='dark'][data-admin-theme-palette='graphite'] {
            background: #121416;
            color: #eef1f5;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: inherit;
            color: inherit;
            font-family: 'IBM Plex Sans', 'Instrument Sans', 'Segoe UI', Tahoma, sans-serif;
        }

        #admin-app {
            min-height: 100vh;
            background: inherit;
            color: inherit;
        }

        .admin-boot {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px 1fr;
            background: inherit;
        }

        .admin-boot__sidebar {
            padding: 24px 20px;
            border-right: 1px solid color-mix(in srgb, currentColor 12%, transparent);
            background: color-mix(in srgb, currentColor 4%, transparent);
        }

        .admin-boot__main {
            padding: 20px 24px;
            display: grid;
            grid-template-rows: 72px 1fr;
            gap: 20px;
        }

        .admin-boot__card,
        .admin-boot__line {
            border-radius: 14px;
            background: color-mix(in srgb, currentColor 10%, transparent);
        }

        .admin-boot__logo {
            width: 148px;
            height: 18px;
            margin-bottom: 28px;
        }

        .admin-boot__nav {
            display: grid;
            gap: 10px;
        }

        .admin-boot__line {
            height: 40px;
        }

        .admin-boot__header {
            height: 72px;
        }

        .admin-boot__content {
            min-height: 320px;
        }

        @media (max-width: 960px) {
            .admin-boot {
                grid-template-columns: 1fr;
            }

            .admin-boot__sidebar {
                display: none;
            }

            .admin-boot__main {
                padding: 16px;
            }
        }
    </style>
    @vite(['resources/css/app.scss', 'resources/js/admin/app.js'])
    <script nonce="{{ $cspNonce ?? request()->attributes->get('csp_nonce', '') }}">
        window.__CMS_ADMIN_BOOTSTRAP = @json($adminBootstrap);
    </script>
</head>
<body>
    <div id="admin-app">
        <div class="admin-boot" aria-hidden="true">
            <aside class="admin-boot__sidebar">
                <div class="admin-boot__card admin-boot__logo"></div>
                <div class="admin-boot__nav">
                    <div class="admin-boot__line"></div>
                    <div class="admin-boot__line"></div>
                    <div class="admin-boot__line"></div>
                    <div class="admin-boot__line"></div>
                </div>
            </aside>

            <main class="admin-boot__main">
                <div class="admin-boot__card admin-boot__header"></div>
                <div class="admin-boot__card admin-boot__content"></div>
            </main>
        </div>
    </div>
</body>
</html>