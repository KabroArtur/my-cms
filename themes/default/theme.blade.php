<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta_title ?? $page->title ?? 'CMS Site' }}</title>
    @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
    <style>
        :root {
            color-scheme: light;
            --site-bg: #f7f6f1;
            --site-surface: #fffdf7;
            --site-border: #d8d2c2;
            --site-text: #1c1917;
            --site-muted: #6b645c;
            --site-accent: #8b5e34;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            background: linear-gradient(180deg, #ebe5d8 0%, var(--site-bg) 280px);
            color: var(--site-text);
        }

        .site-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        .site-header {
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--site-border);
        }

        .site-kicker {
            margin: 0 0 8px;
            color: var(--site-muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 12px;
            font-weight: 700;
        }

        .site-title {
            margin: 0 0 12px;
            font-size: clamp(34px, 7vw, 54px);
            line-height: 1;
        }

        .site-excerpt {
            margin: 0;
            max-width: 720px;
            color: var(--site-muted);
            font-size: 18px;
            line-height: 1.6;
        }

        .site-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0 28px;
        }

        .site-meta span {
            display: inline-flex;
            padding: 8px 12px;
            border: 1px solid var(--site-border);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            color: var(--site-muted);
            font-size: 13px;
        }

        .site-featured-media {
            margin: 0 0 28px;
            border: 1px solid var(--site-border);
            border-radius: 24px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.68);
            box-shadow: 0 20px 60px rgba(28, 25, 23, 0.06);
        }

        .site-featured-media img {
            display: block;
            width: 100%;
            max-height: 480px;
            object-fit: cover;
        }

        .site-content {
            padding: 28px;
            border: 1px solid var(--site-border);
            border-radius: 24px;
            background: var(--site-surface);
            box-shadow: 0 20px 60px rgba(28, 25, 23, 0.06);
            line-height: 1.75;
            font-size: 18px;
        }

        .site-content :first-child {
            margin-top: 0;
        }

        .site-content :last-child {
            margin-bottom: 0;
        }

        .site-footer {
            margin-top: 24px;
            color: var(--site-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main class="site-shell">
        <header class="site-header">
            <p class="site-kicker">{{ $page->template ?? 'default' }}</p>
            <h1 class="site-title">{{ $page->title ?? 'Home' }}</h1>

            @if ($page->excerpt)
                <p class="site-excerpt">{{ $page->excerpt }}</p>
            @endif
        </header>

        <div class="site-meta">
            <span>Slug: /{{ $page->slug }}</span>
            <span>Status: {{ $page->status?->value ?? $page->status }}</span>
            <span>Published: {{ optional($page->published_at)->format('d.m.Y H:i') ?? 'not scheduled' }}</span>
        </div>

        @if ($page->featuredMedia)
            <figure class="site-featured-media">
                <img src="{{ $page->featuredMedia->url() }}" alt="{{ $page->featuredMedia->alt_text ?: $page->featuredMedia->original_name }}">
                @if ($page->featuredMedia->caption)
                    <figcaption style="padding: 12px 16px; color: var(--site-muted); font-size: 14px;">{{ $page->featuredMedia->caption }}</figcaption>
                @endif
            </figure>
        @endif

        <article class="site-content">
            {!! $page->content ?: nl2br(e($page->excerpt ?? 'Контент страницы пока не заполнен.')) !!}
        </article>

        <footer class="site-footer">
            <p>Страница рендерится из CMS через тему по slug.</p>
        </footer>
    </main>
</body>
</html>