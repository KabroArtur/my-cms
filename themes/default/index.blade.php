<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
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

        .site-nav {
            margin: 0 0 28px;
            padding: 18px 20px;
            border: 1px solid var(--site-border);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.68);
            box-shadow: 0 16px 40px rgba(28, 25, 23, 0.05);
        }

        .site-nav h2 {
            margin: 0 0 12px;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--site-muted);
        }

        .site-nav__tree,
        .site-nav__children {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 8px;
        }

        .site-nav__children {
            margin-top: 10px;
            padding-left: 18px;
            border-left: 1px solid var(--site-border);
        }

        .site-nav__link {
            color: var(--site-text);
            text-decoration: none;
        }

        .site-nav__item.is-current > .site-nav__link,
        .site-nav__item.is-ancestor > .site-nav__link {
            color: var(--site-accent);
            font-weight: 700;
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

        .site-posts {
            margin-top: 24px;
            padding: 24px;
            border: 1px solid var(--site-border);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.72);
        }

        .site-posts__list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .site-posts__item {
            padding-bottom: 14px;
            border-bottom: 1px solid var(--site-border);
        }

        .site-posts__item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .site-posts__meta {
            color: var(--site-muted);
            font-size: 13px;
        }
    </style>
</head>
<body {!! attr(['class' => body_class('site-body')]) !!}>

    <main class="site-shell">
        {!! component('components/header') !!}

        <div class="site-meta">
            <span>Slug: /{{ slug() }}</span>
            <span>Status: {{ status() }}</span>
            <span>Published: {{ page_date() ?: 'not scheduled' }}</span>
        </div>

        @if (has_image('featured_image'))
            <figure class="site-featured-media">
                {!! image('featured_image', ['size' => setting('site_featured_media_variant', 'original')]) !!}
            </figure>
        @endif

        <article class="site-content">
            {!! content(new \Illuminate\Support\HtmlString(nl2br(e(excerpt('Контент страницы пока не заполнен.'))))) !!}
        </article>

        @php($latestPosts = posts(['limit' => 3]))
        @if ($latestPosts !== [])
            <section class="site-posts">
                <h2>Свежие записи блога</h2>
                <ul class="site-posts__list">
                    @foreach ($latestPosts as $post)
                        <li class="site-posts__item">
                            <h3><a href="{{ $post->url }}">{{ $post->title }}</a></h3>
                            @if ($post->excerpt !== '')
                                <p>{{ $post->excerpt }}</p>
                            @endif
                            <p class="site-posts__meta">
                                Категория: {{ $post->category?->name ?? 'Без категории' }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>