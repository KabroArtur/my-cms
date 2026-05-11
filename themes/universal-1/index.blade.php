<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
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

                <article class="zerno-content">
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
