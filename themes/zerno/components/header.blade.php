<header class="zerno-header">
    <div class="zerno-header__row">
        <div>
            <p class="zerno-header__eyebrow">{{ site_name() }}</p>
            <strong class="zerno-header__brand">Zerno Theme</strong>
        </div>
        <div class="zerno-header__stamp">Сектор: агропром</div>
    </div>

    {!! menu('main', [
        'container' => 'nav',
        'container_class' => 'zerno-nav',
        'container_attrs' => ['aria-label' => 'Основная навигация'],
        'list' => true,
        'list_tag' => 'ul',
        'list_class' => 'zerno-nav__list',
        'item_tag' => 'li',
        'item_class' => 'zerno-nav__item',
        'active_class' => 'is-current',
        'ancestor_class' => 'is-ancestor',
        'link_class' => 'zerno-nav__link',
        'children_tag' => 'ul',
        'children_class' => 'zerno-nav__children',
    ]) !!}
</header>
