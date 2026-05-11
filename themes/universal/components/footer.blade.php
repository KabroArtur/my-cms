<footer class="footer">
    <div class="container footer__container">
        <div>
            <a href="{{ home_url() }}" class="footer__logo" aria-label="{{ site_name() }}">
                @if (setting_image('site_logo'))
                    <img src="{{ setting_image('site_logo') }}" alt="{{ site_name() }}">
                @else
                    {{ site_name() }}
                @endif
            </a>
            <p class="footer__text">Технологический профиль: агропромышленная инфраструктура и контентная тема для CMS.</p>
        </div>

        <div>
            <p class="footer__title">Навигация</p>
            <ul class="footer__list">
                <li><a href="{{ home_url() }}">Главная</a></li>
                <li><a href="{{ url('/login') }}">Вход</a></li>
            </ul>
        </div>

        <div>
            <p class="footer__title">Статус</p>
            <ul class="footer__list">
                <li>Шаблон: {{ template('default') }}</li>
                <li>Язык: {{ lang() }}</li>
            </ul>
        </div>

        <div>
            <p class="footer__title">Обновление</p>
            <ul class="footer__list">
                <li>{{ date_now('d.m.Y') }}</li>
                <li>{{ date_now('H:i') }}</li>
            </ul>
        </div>
    </div>

    <div class="container footer__bottom">
        <p>{{ site_name() }} · Zerno Theme</p>
    </div>
</footer>
