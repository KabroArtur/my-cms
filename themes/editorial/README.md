# Editorial Theme: README по макросам и навигации

Этот файл объясняет, как работать с макросами темы так, чтобы это было понятно даже новичку.

## 1. Главный принцип

Макросы в этом проекте data-first:
- backend отдает данные о страницах;
- тема сама решает, какие теги, классы и атрибуты использовать.

Иначе говоря: ядро не навязывает ul/li, классы и структуру HTML.

## 2. Где что находится

- Главный шаблон темы: themes/editorial/theme.blade.php
- Рендер корня меню: themes/editorial/partials/navigation-tree.blade.php
- Рендер пункта меню: themes/editorial/partials/navigation-item.blade.php
- Источник данных макросов: app/Core/Themes/Services/ThemeRuntime.php
- Blade-директивы (если нужны): app/Core/Themes/ThemesModule.php

## 3. Что такое "макрос" в этой теме

В теме доступны два стиля работы:

1) Рекомендуемый (понятный): через объект $cms
- Пример: $cms->menu($page)

2) Совместимый стиль: через Blade-директивы
- Пример: @cmsMenu($menu, $page)

Для новых шаблонов рекомендуется использовать первый вариант.

## 4. Все доступные макросы и методы

Ниже список из ThemeRuntime.

### 4.1. siteName()
Возвращает название сайта из настроек.

Пример:

```php
@php($name = $cms->siteName())
<h1>{{ $name }}</h1>
```

### 4.2. setting(key, default)
Возвращает значение настройки по ключу.

Пример:

```php
@php($dateFormat = $cms->setting('date_format', 'd.m.Y'))
```

### 4.3. menu(currentPage)
Главный метод для меню. Возвращает древовидный массив пунктов.

Пример:

```php
@php($menu = $cms->menu($page))
```

### 4.4. menuTree(currentPage)
То же дерево меню, что и menu(). Используйте menu() как более понятное имя.

### 4.5. children(pageOrNode, currentPage)
Возвращает только дочерние пункты выбранного узла.

Пример:

```php
@php($children = $cms->children($page, $page))
```

### 4.6. breadcrumbs(page)
Возвращает хлебные крошки от корня до текущей страницы.

Пример:

```php
@php($crumbs = $cms->breadcrumbs($page))
```

### 4.7. pageUrl(pageOrNode)
Возвращает URL для страницы или узла массива.

Пример:

```php
@php($url = $cms->pageUrl($page))
```

## 5. Формат пункта меню

Каждый пункт в дереве меню содержит:

- id
- title
- url
- path
- is_home
- depth
- is_current
- is_ancestor
- children (массив дочерних пунктов)

Пример структуры:

```php
[
  'id' => 10,
  'title' => 'О компании',
  'url' => '/company',
  'path' => 'company',
  'is_home' => false,
  'depth' => 0,
  'is_current' => false,
  'is_ancestor' => true,
  'children' => [...],
]
```

## 6. Единые правила по кавычкам

Чтобы не было путаницы:

- Для строковых ключей и literal-значений используем одинарные кавычки.
- Для переменных кавычки не ставим.
- Для названий CSS-классов в конфиге menuOptions используем строки в одинарных кавычках.

Хорошо:

```php
@php($menu = $cms->menu($page))
@php($format = $cms->setting('date_format', 'd.m.Y'))
```

Плохо:

```php
@php($menu = $cms->menu('$page'))
```

## 7. Как устроен рендер меню сейчас

В themes/editorial/theme.blade.php:

1) Получаем данные:

```php
@php($menu = $cms->menu($page))
```

2) Описываем поведение рендера через menuOptions.

3) Вызываем общий partial:

```php
@include('theme::partials.navigation-tree', ['items' => $menu, 'menuOptions' => $menuOptions])
```

## 8. menuOptions: что за что отвечает

Конфиг menuOptions делится на блоки.

### 8.1. Корневой контейнер

- root_tag
- root_class
- root_attributes
- before_root
- after_root

### 8.2. Пункт меню

- item_tag
- item_class
- item_class_current
- item_class_ancestor
- item_attributes
- before_item
- after_item

### 8.3. Ссылка

- link_tag
- link_class
- link_attributes
- before_link
- after_link

### 8.4. Контейнер детей

- children_tag
- children_class
- children_attributes
- before_children
- after_children

## 9. Где добавлять свои классы в цикле

В этом проекте лучше добавлять классы через menuOptions, а не менять логику цикла.

Пример:

```php
$menuOptions = [
  'item_class' => 'editorial-nav__group my-item',
  'item_class_current' => 'is-current active',
  'link_class' => 'editorial-nav__link my-link',
  'children_class' => 'editorial-nav__children my-children',
];
```

Если нужно, можно также добавить class внутри attributes:

```php
$menuOptions = [
  'item_attributes' => [
    'class' => 'extra-item-class',
  ],
];
```

Оба класса будут объединены.

## 10. Где добавлять свои атрибуты

Добавляйте их в *_attributes секции.

Пример:

```php
$menuOptions = [
  'root_attributes' => [
    'data-nav' => 'main',
    'aria-label' => 'Главное меню',
  ],
  'link_attributes' => [
    'data-track' => 'menu-click',
  ],
  'item_attributes' => [
    'data-role' => 'menu-item',
  ],
  'children_attributes' => [
    'data-level' => 'child',
  ],
];
```

Особые случаи:
- null и false не рендерятся;
- true рендерится как boolean-атрибут без значения.

## 11. Before/after обертки

Before/after нужны, когда нужно добавить обертку до или после узла без изменения внутренней логики partial.

Доступные ключи:
- before_root / after_root
- before_item / after_item
- before_link / after_link
- before_children / after_children

Поддерживаемые токены:
- {id}
- {title}
- {url}
- {path}

Пример:

```php
$menuOptions = [
  'before_item' => '<!-- item:{id}:{title} -->',
  'after_item' => '',
  'before_link' => '<span class="dot" aria-hidden="true">•</span>',
  'after_link' => '',
];
```

## 12. Как поменять теги (не использовать ul/li)

Можно полностью переключить структуру, например на div/div/a:

```php
$menuOptions = [
  'root_tag' => 'div',
  'item_tag' => 'div',
  'link_tag' => 'a',
  'children_tag' => 'div',
];
```

Можно вернуть классический список:

```php
$menuOptions = [
  'root_tag' => 'ul',
  'item_tag' => 'li',
  'children_tag' => 'ul',
];
```

## 13. Когда параметры можно не задавать

Любой ключ menuOptions можно не указывать.

Если ключ пропущен:
- partial подставит безопасный дефолт;
- рендер не упадет;
- меню продолжит работать.

Это специально сделано для простого старта.

## 14. Простой стартовый шаблон (копируй и меняй)

```php
@php
  $menu = $cms->menu($page);

  $menuOptions = [
    'root_tag' => 'div',
    'root_class' => 'editorial-nav__list',
    'root_attributes' => [],

    'item_tag' => 'div',
    'item_class' => 'editorial-nav__group',
    'item_class_current' => 'is-current',
    'item_class_ancestor' => 'is-ancestor',
    'item_attributes' => [],

    'link_tag' => 'a',
    'link_class' => 'editorial-nav__link',
    'link_attributes' => [],

    'children_tag' => 'div',
    'children_class' => 'editorial-nav__children',
    'children_attributes' => [],

    'before_root' => '',
    'after_root' => '',
    'before_item' => '',
    'after_item' => '',
    'before_link' => '',
    'after_link' => '',
    'before_children' => '',
    'after_children' => '',
  ];
@endphp

@if ($menu !== [])
  @include('theme::partials.navigation-tree', ['items' => $menu, 'menuOptions' => $menuOptions])
@endif
```

## 15. Blade-директивы (если очень нужно)

В проекте есть совместимые директивы:
- @cmsSiteName
- @cmsSetting(...)
- @cmsMenu(...)
- @cmsBreadcrumbs(...)
- @cmsChildren(...)

Но для новых тем предпочтительнее стиль через $cms, потому что:
- проще читать;
- проще отлаживать;
- меньше магии в шаблоне.

Пример директивы:

```php
@cmsMenu($menu, $page)
```

После этого переменная $menu будет доступна в шаблоне.

## 16. Почему архитектура именно такая

- Переиспользование: один backend API, много разных тем.
- Гибкость: любые теги и структура без правок ядра.
- Порог входа: начинающий видит обычный Blade с понятным конфигом.
- Безопасность изменений: меняем тему, не трогаем core.

## 17. Частые ошибки

1) Передали строку вместо переменной страницы:
- Неправильно: $cms->menu('$page')
- Правильно: $cms->menu($page)

2) Забыли передать menuOptions в include:
- Тогда часть кастомизаций не применится.

3) Смешали два стиля в одном месте:
- Лучше выбрать один стиль через $cms.

## 18. Мини-чеклист перед коммитом

- Меню рендерится на главной и вложенной странице.
- У текущего пункта есть item_class_current.
- У родителя текущего пункта есть item_class_ancestor.
- Кастомные атрибуты реально попали в HTML.
- Before/after не ломают структуру.
- Нет привязки к конкретному тегу, если это не нужно.