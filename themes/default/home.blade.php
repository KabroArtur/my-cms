{{-- CMS_TEMPLATE: home|Главная страница --}}
{{-- CMS_TEMPLATE_DESCRIPTION: Отдельный шаблон главной страницы для стартовых блоков и акцентов. --}}
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
            background: radial-gradient(circle at top, rgba(139, 94, 52, 0.16), transparent 36%), linear-gradient(180deg, #ebe5d8 0%, var(--site-bg) 320px);
            color: var(--site-text);
        }

        .site-shell {
            max-width: 1040px;
            margin: 0 auto;
            padding: 32px 20px 80px;
        }

        .site-home-hero {
            margin: 0 0 28px;
            padding: 28px;
            border: 1px solid var(--site-border);
            border-radius: 28px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(245, 238, 225, 0.92));
            box-shadow: 0 24px 60px rgba(28, 25, 23, 0.08);
        }

        .site-home-badge {
            display: inline-flex;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(139, 94, 52, 0.12);
            color: var(--site-accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .site-home-grid {
            display: grid;
            gap: 24px;
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
            margin: 0;
            border: 1px solid var(--site-border);
            border-radius: 24px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.68);
            box-shadow: 0 20px 60px rgba(28, 25, 23, 0.06);
        }

        .site-featured-media img {
            display: block;
            width: 100%;
            max-height: 520px;
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

        .site-posts {
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
<body {!! attr(['class' => body_class('site-body site-body--home')]) !!}>

    <main class="site-shell">
        {!! component('components/header') !!}

        <section class="site-home-hero">
            <span class="site-home-badge">Home template</span>
            <h1>{{ title('Главная страница') }}</h1>
            <p>{{ excerpt('Этот шаблон можно выбрать прямо на странице и использовать в правилах дополнительных полей.') }}</p>
        </section>

        <div class="site-home-grid">
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

            @php($latestPosts = posts(['limit' => 4]))
            @if ($latestPosts !== [])
                <section class="site-posts">
                    <h2>Последние записи</h2>
                    <ul class="site-posts__list">
                        @foreach ($latestPosts as $post)
                            <li class="site-posts__item">
                                <h3><a href="{{ $post->url }}">{{ $post->title }}</a></h3>
                                @if ($post->excerpt !== '')
                                    <p>{{ $post->excerpt }}</p>
                                @endif
                                <p class="site-posts__meta">{{ $post->category?->name ?? 'Без категории' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>