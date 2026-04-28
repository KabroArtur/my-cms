<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?? $page->title ?? ($settings['site_name'] ?? 'CMS Site') }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    @if (! empty($settings['favicon_url']))
        <link rel="icon" href="{{ $settings['favicon_url'] }}">
    @endif
    <style>
        :root {
            color-scheme: light;
            --editorial-bg: #f2eee6;
            --editorial-surface: #fffdfa;
            --editorial-border: #d7cbb9;
            --editorial-text: #241d18;
            --editorial-muted: #75685b;
            --editorial-accent: #9a4f2a;
            --editorial-accent-soft: #efe1d7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Georgia', 'Times New Roman', serif;
            background:
                radial-gradient(circle at top left, #f6e6d9 0, transparent 34%),
                linear-gradient(180deg, #e9dfd1 0, var(--editorial-bg) 320px);
            color: var(--editorial-text);
        }

        .editorial-shell {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 88px;
        }

        .editorial-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
            color: var(--editorial-muted);
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .editorial-topbar strong {
            color: var(--editorial-accent);
        }

        .editorial-topbar nav {
            min-width: 0;
        }

        .editorial-nav__list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .editorial-nav__group {
            display: grid;
            gap: 8px;
        }

        .editorial-nav__link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            color: var(--editorial-text);
            text-decoration: none;
            background: rgba(255, 255, 255, 0.56);
            border: 1px solid transparent;
        }

        .editorial-nav__group.is-current > .editorial-nav__link,
        .editorial-nav__group.is-ancestor > .editorial-nav__link {
            color: var(--editorial-accent);
            border-color: var(--editorial-border);
            background: color-mix(in srgb, var(--editorial-accent-soft) 70%, white);
        }

        .editorial-nav__children {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding-left: 12px;
        }

        .editorial-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.7fr);
            gap: 24px;
        }

        .editorial-hero,
        .editorial-sidebar-card,
        .editorial-content {
            border: 1px solid var(--editorial-border);
            background: color-mix(in srgb, var(--editorial-surface) 92%, white);
            box-shadow: 0 24px 70px rgba(36, 29, 24, 0.08);
        }

        .editorial-hero {
            padding: 30px;
            border-radius: 34px;
        }

        .editorial-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 16px;
            padding: 9px 14px;
            border-radius: 999px;
            background: var(--editorial-accent-soft);
            color: var(--editorial-accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .editorial-title {
            margin: 0 0 18px;
            font-size: clamp(42px, 8vw, 76px);
            line-height: 0.92;
            max-width: 12ch;
        }

        .editorial-excerpt {
            margin: 0;
            max-width: 58ch;
            color: var(--editorial-muted);
            font-size: 20px;
            line-height: 1.7;
        }

        .editorial-sidebar {
            display: grid;
            gap: 18px;
            align-content: start;
        }

        .editorial-sidebar-card {
            padding: 20px 22px;
            border-radius: 24px;
        }

        .editorial-sidebar-card h2 {
            margin: 0 0 12px;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--editorial-muted);
        }

        .editorial-meta-list {
            display: grid;
            gap: 10px;
            margin: 0;
        }

        .editorial-meta-row {
            display: grid;
            gap: 3px;
        }

        .editorial-meta-row dt {
            color: var(--editorial-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .editorial-meta-row dd {
            margin: 0;
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .editorial-featured-media {
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid var(--editorial-border);
            background: white;
        }

        .editorial-featured-media img {
            display: block;
            width: 100%;
            max-height: 520px;
            object-fit: cover;
        }

        .editorial-featured-media figcaption {
            padding: 14px 18px;
            color: var(--editorial-muted);
            font-size: 14px;
        }

        .editorial-content {
            margin-top: 24px;
            padding: 28px 30px;
            border-radius: 30px;
            line-height: 1.85;
            font-size: 19px;
        }

        .editorial-content :first-child {
            margin-top: 0;
        }

        .editorial-content :last-child {
            margin-bottom: 0;
        }

        .editorial-footer {
            margin-top: 22px;
            color: var(--editorial-muted);
            font-size: 14px;
        }

        @media (max-width: 980px) {
            .editorial-grid {
                grid-template-columns: 1fr;
            }

            .editorial-shell {
                width: min(100%, calc(100% - 24px));
            }
        }
    </style>
</head>
<body {!! cms_body_attrs(['class' => 'editorial-body']) !!}>

    <main class="editorial-shell">
        <div class="editorial-topbar">
            <span><strong>{{ cms_site_name() }}</strong></span>
            <span>Editorial theme</span>
        </div>

        {!! cms_menu('main', [
            'container' => 'nav',
            'container_class' => 'editorial-nav editorial-nav__list',
            'container_attrs' => ['aria-label' => 'Site navigation', 'data-nav' => 'main'],
            'list' => false,
            'item_tag' => 'div',
            'item_class' => 'editorial-nav__group',
            'active_class' => 'is-current',
            'ancestor_class' => 'is-ancestor',
            'link_class' => 'editorial-nav__link',
            'children_tag' => 'div',
            'children_class' => 'editorial-nav__children',
        ]) !!}

        <section class="editorial-grid">
            <div>
                <header class="editorial-hero">
                    <p class="editorial-kicker">{{ cms_template(cms_setting('site_theme', 'editorial')) }}</p>
                    <h1 class="editorial-title">{{ cms_title('Home') }}</h1>

                    @if (cms_has_field('excerpt'))
                        <p class="editorial-excerpt">{{ cms_excerpt() }}</p>
                    @endif
                </header>

                @if (cms_has_image('featured_image'))
                    <figure class="editorial-featured-media" style="margin: 24px 0 0;">
                        {!! cms_image('featured_image', ['size' => cms_setting('site_featured_media_variant', 'original')]) !!}
                    </figure>
                @endif

                <article class="editorial-content">
                    {!! cms_content(new \Illuminate\Support\HtmlString(nl2br(e(cms_excerpt('Контент страницы пока не заполнен.'))))) !!}
                </article>
            </div>

            <aside class="editorial-sidebar">
                <section class="editorial-sidebar-card">
                    <h2>Публикация</h2>
                    <dl class="editorial-meta-list">
                        <div class="editorial-meta-row">
                            <dt>Путь</dt>
                            <dd>{{ cms_is_home() ? '/' : '/'.cms_path() }}</dd>
                        </div>
                        <div class="editorial-meta-row">
                            <dt>Статус</dt>
                            <dd>{{ cms_status() }}</dd>
                        </div>
                        <div class="editorial-meta-row">
                            <dt>Опубликовано</dt>
                            <dd>{{ cms_date() ?: 'not scheduled' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="editorial-sidebar-card">
                    <h2>Тема</h2>
                    <p style="margin: 0; color: var(--editorial-muted); line-height: 1.7;">Страница рендерится через альтернативную журнальную тему. Навигация здесь собрана уже без дефолтных ul/li из ядра, полностью на разметке темы.</p>
                </section>
            </aside>
        </section>

        <footer class="editorial-footer">
            <p>{{ cms_site_name() }}. Публичный шаблон: Editorial Theme.</p>
        </footer>
    </main>

    {!! cms_footer() !!}
</body>
</html>