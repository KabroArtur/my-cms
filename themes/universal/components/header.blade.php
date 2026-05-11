  <header class="header">
    <div class="container header__container">
      <a href="{{ home_url() }}" class="logo" aria-label="{{ site_name() }}">
          @if (setting_image('site_logo'))
              <img src="{{ setting_image('site_logo') }}" alt="{{ site_name() }}" width="120" height="70" fetchpriority="high">
          @else
              {{ site_name() }}
          @endif
      </a>

         {!! menu('main', [
    'container' => 'nav',
    'container_class' => 'nav',
    'container_attrs' => [
        'id' => 'nav',
        'aria-label' => 'Main navigation',
    ],

    'list' => true,
    'list_tag' => 'ul',
    'list_class' => 'nav__list',

    'item_tag' => 'li',
    'item_class' => 'nav__item',
    'active_class' => 'is-current',
    'ancestor_class' => 'is-ancestor',

    'link_class' => 'nav__link',

    'children_tag' => 'ul',
    'children_class' => 'nav__dropdown',
]) !!}

      <button class="burger" type="button" aria-label="Open menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </header>
