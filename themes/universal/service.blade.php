{{-- CMS_TEMPLATE: service|Сервисная страница --}}
{{-- CMS_TEMPLATE_DESCRIPTION: Пример отдельного шаблона для услуг, сервиса или направления работ. --}}
<section class="zerno-hero">
    <p class="zerno-kicker">Сервисное направление</p>
    <h1 class="zerno-title">{{ title('Услуги и сопровождение') }}</h1>
    <p class="zerno-lead">{{ excerpt('Этот шаблон показывает, как делать отдельные страницы без общего дублирования документа.') }}</p>
</section>

<section class="zerno-layout">
    <div class="zerno-main-column">
        <section class="zerno-panel">
            <h2 class="zerno-panel__title">Что входит в сервис</h2>
            <p class="zerno-note">Здесь можно описать услугу, процесс сопровождения, SLA, сезонные условия и дополнительные преимущества.</p>
        </section>

        @if (has_image('featured_image'))
            <figure class="zerno-media">
                {!! image('featured_image', ['size' => setting('site_featured_media_variant', 'original')]) !!}
            </figure>
        @endif

        <article class="zerno-content">
            {!! content(new \Illuminate\Support\HtmlString(nl2br(e(excerpt('Добавьте основной текст услуги, этапы работы, кейсы и блок с преимуществами.'))))) !!}
        </article>
    </div>

    <aside class="zerno-side-column">
        <section class="zerno-panel">
            <h2 class="zerno-panel__title">Карточка страницы</h2>
            <dl class="zerno-metrics">
                <div class="zerno-metrics__row">
                    <dt>Шаблон</dt>
                    <dd>{{ template('service') }}</dd>
                </div>
                <div class="zerno-metrics__row">
                    <dt>Путь</dt>
                    <dd>{{ is_home() ? '/' : '/'.path() }}</dd>
                </div>
                <div class="zerno-metrics__row">
                    <dt>Статус</dt>
                    <dd>{{ status() }}</dd>
                </div>
            </dl>
        </section>

        <section class="zerno-panel">
            <h2 class="zerno-panel__title">Как использовать</h2>
            <p class="zerno-note">Создайте страницу в админке и выберите шаблон «Сервисная страница». Шапка и footer подтянутся автоматически из index.</p>
        </section>
    </aside>
</section>