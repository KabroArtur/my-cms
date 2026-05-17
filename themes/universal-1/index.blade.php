<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
    <style>
        .theme-page-content {
            width: min(1100px, calc(100% - 32px));
            margin: 32px auto;
        }

        .theme-page-content video {
            display: block;
            width: 100%;
            max-width: 100%;
            margin: 0 0 20px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.14);
            background: #0f172a;
        }

        .theme-page-content a[download] {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 600;
        }

        .theme-page-content a[download]::before {
            content: "Файл";
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }
    </style>
</head>
<body {!! attr(['class' => 'zerno-body']) !!}>

    <main class="zerno-shell">
        {!! component('components/header') !!}

        <section class="zerno-hero">
            <p class="zerno-kicker">Агропромышленный портал</p>
            <h1 class="zerno-title">{{ title('Zerno') }}</h1>

            @if (has_field('excerpt') || excerpt() !== '')
                <p class="zerno-lead">{{ excerpt('Оперативная информация по производству, логистике и сезонным работам.') }}</p>
            @endif
        </section>

        <section class="zerno-layout">
            <div class="zerno-main-column">
                @if (has_image('featured_image'))
                    <figure class="zerno-media">
                        {!! image('featured_image', ['size' => setting('site_featured_media_variant', 'original')]) !!}
                    </figure>
                @endif

                <article class="zerno-content theme-page-content">
                    {!! content(new \Illuminate\Support\HtmlString(nl2br(e(excerpt('Контент страницы пока не заполнен.'))))) !!}
                </article>
            </div>

            <aside class="zerno-side-column">
                <section class="zerno-panel">
                    <h2 class="zerno-panel__title">Сводка</h2>
                    <dl class="zerno-metrics">
                        <div class="zerno-metrics__row">
                            <dt>Путь</dt>
                            <dd>{{ is_home() ? '/' : '/'.path() }}</dd>
                        </div>
                        <div class="zerno-metrics__row">
                            <dt>Статус</dt>
                            <dd>{{ status() }}</dd>
                        </div>
                        <div class="zerno-metrics__row">
                            <dt>Публикация</dt>
                            <dd>{{ page_date() ?: 'not scheduled' }}</dd>
                        </div>
                        <div class="zerno-metrics__row">
                            <dt>Таймзона</dt>
                            <dd>{{ date_timezone() }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="zerno-panel">
                    <h2 class="zerno-panel__title">Профиль площадки</h2>
                    <p class="zerno-note">Структура шаблона ориентирована на агро-производство: спокойная сетка, контрастные панели, акцент на данные и читаемость.</p>
                </section>
            </aside>
        </section>

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>
