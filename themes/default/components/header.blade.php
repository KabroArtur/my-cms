<header class="site-header">
    <p class="site-kicker">{{ site_name() }} / {{ template(setting('site_theme', 'default')) }}</p>
    <h1 class="site-title">{{ title('Home') }}</h1>

    @if (has_field('excerpt'))
        <p class="site-excerpt">{{ excerpt() }}</p>
    @endif
</header>

<nav class="site-nav" aria-label="Site navigation">
    <h2>Страницы сайта</h2>
    {!! menu('main', [
        'container' => null,
        'list' => true,
        'list_tag' => 'ul',
        'list_class' => 'site-nav__tree',
        'item_tag' => 'li',
        'item_class' => 'site-nav__item',
        'active_class' => 'is-current',
        'ancestor_class' => 'is-ancestor',
        'link_class' => 'site-nav__link',
        'children_tag' => 'ul',
        'children_class' => 'site-nav__children',
    ]) !!}
</nav>
