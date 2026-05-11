{{-- CMS_TEMPLATE: home|Главная страница --}}
{{-- CMS_TEMPLATE_DESCRIPTION: Акцентный шаблон главной страницы для editorial-темы. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
</head>
<body {!! attr(['class' => body_class('zerno-body zerno-body--home')]) !!}>

    <main class="zerno-shell">
        {!! component('components/header') !!}

        <section class="zerno-hero">
            <p class="zerno-kicker">Home template</p>
            <h1 class="zerno-title">{{ title('Главная страница') }}</h1>
            <p class="zerno-lead">{{ excerpt('Отдельный шаблон главной страницы доступен в списке шаблонов и в правилах дополнительных полей.') }}</p>
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
                            <dt>Шаблон</dt>
                            <dd>{{ template('default') }}</dd>
                        </div>
                        <div class="zerno-metrics__row">
                            <dt>Публикация</dt>
                            <dd>{{ page_date() ?: 'not scheduled' }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </section>

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>