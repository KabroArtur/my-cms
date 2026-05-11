{{-- CMS_TEMPLATE: home|Главная страница --}}
{{-- CMS_TEMPLATE_DESCRIPTION: Акцентный шаблон главной страницы для editorial-темы. --}}

<section class="hero" data-animate>
            <div class="container hero__container">

                <div class="hero__content">

                    @if(has_field('hero_label'))
                        <span class="hero__label">
                            {{ field('hero_label') }}
                        </span>
                    @endif

                    <h1 class="hero__title">
                        {{ field('hero_title', title('Build your website faster')) }}
                    </h1>

                    @if(has_field('hero_text'))
                        <p class="hero__text">
                            {{ field('hero_text') }}
                        </p>
                    @endif

                    <div class="hero__actions">

                        @if(has_field('hero_button_primary_text'))
                            <a href="{{ field('hero_button_primary_url', '#') }}"
                               class="button button--primary">
                                {{ field('hero_button_primary_text') }}
                            </a>
                        @endif

                        @if(has_field('hero_button_secondary_text'))
                            <a href="{{ field('hero_button_secondary_url', '#') }}"
                               class="button button--secondary">
                                {{ field('hero_button_secondary_text') }}
                            </a>
                        @endif

                    </div>
                </div>

                <div class="hero__media">

                    @if(has_image('hero_image'))
                        {!! image('hero_image', [
                            'size' => 'original',
                            'loading' => 'eager',
                            'fetchpriority' => 'high',
                        ]) !!}
                    @endif

                    @if(has_field('hero_badge_title') || has_field('hero_badge_text'))
                        <div class="hero__badge">

                            @if(has_field('hero_badge_title'))
                                <span>{{ field('hero_badge_title') }}</span>
                            @endif

                            @if(has_field('hero_badge_text'))
                                <p>{{ field('hero_badge_text') }}</p>
                            @endif

                        </div>
                    @endif

                </div>

            </div>
        </section>