# resources: как здесь устроена верстка

Я пишу это как рабочую инструкцию для человека, который будет переписывать верстку и стили в админке.

Здесь без канцелярита. Ниже просто и по делу: куда идти, какой файл за что отвечает, где менять кнопки, где менять layout, где менять формы, где стили общие, а где стили конкретной страницы.

Если коротко: почти вся работа по верстке будет в двух местах:

- `resources/js/admin`
- `resources/css/admin`

## С чего начать

Если я открываю проект с нуля и хочу понять, как устроена админка, я иду вот так:

1. `resources/views/admin.blade.php`
2. `resources/js/admin/app.js`
3. `resources/js/admin/router.js`
4. `resources/js/admin/layout/AdminLayout.vue`
5. нужная страница в `resources/js/admin/pages`
6. потом стили в `resources/css/admin/_components.scss` и `resources/css/admin/_pages.scss`

Этого хватает, чтобы понять почти любой экран.

## Что лежит в resources

Внутри `resources` три основные части:

- `resources/views` - Blade-шаблоны Laravel
- `resources/js` - Vue-часть админки
- `resources/css` - стили

Если задача только про верстку админки, то `resources/views` я обычно почти не трогаю, а работаю в:

- `resources/js/admin`
- `resources/css/admin`

## Как админка вообще открывается

Цепочка простая:

1. `resources/views/admin.blade.php` подключает фронтенд через Vite
2. `resources/js/admin/app.js` запускает Vue
3. `resources/js/admin/AdminApp.vue` выводит `router-view`
4. `resources/js/admin/router.js` решает, какую страницу показать
5. страница берется из `resources/js/admin/pages/...`

То есть если я не понимаю, откуда взялся экран, я почти всегда иду сначала в `router.js`.

## Главная карта по папкам

### `resources/js/admin`

Это вся админка на Vue.

Что внутри:

- `app.js` - запуск приложения
- `AdminApp.vue` - корневой Vue-файл
- `router.js` - маршруты
- `layout` - общий каркас админки
- `pages` - сами страницы
- `components` - переиспользуемые куски интерфейса
- `api` - запросы к backend
- `stores` - store
- `composables` - общая логика
- `utils` - утилиты

### `resources/css/admin`

Это все основные стили админки.

Что внутри:

- `_tokens.scss` - цвета, радиусы, отступы, темы
- `_base.scss` - базовые стили, типографика, поля формы
- `_layout.scss` - общий каркас админки
- `_components.scss` - общие компоненты
- `_pages.scss` - стили отдельных экранов

## Куда идти, если нужно переписать конкретную вещь

### Если нужно поменять все кнопки

Иду сюда:

- `resources/js/admin/components/ui/AdminButton.vue`
- `resources/css/admin/_components.scss`

Важно понимать:

- в `AdminButton.vue` лежит структура кнопки
- в `_components.scss` лежит внешний вид кнопки

### Если нужно поменять все формы

Иду сюда:

- `resources/css/admin/_base.scss`

Там лежат основные классы:

- `.admin-input`
- `.admin-select`
- `.admin-textarea`
- `.admin-form-label`
- `.admin-actions-row`

### Если нужно поменять модалки

Сначала смотрю:

- `resources/css/admin/_components.scss`

Потом уже конкретный Vue-файл модалки, если у нее есть свои особые стили или разметка.

Основные классы модалок лежат тут:

- `.admin-modal`
- `.admin-modal__dialog`
- `.admin-modal__header`
- `.admin-modal__body`

### Если нужно поменять весь каркас админки

Иду сюда:

- `resources/js/admin/layout/AdminLayout.vue`
- `resources/css/admin/_layout.scss`

Там лежат:

- левое меню
- верхняя панель
- хлебные крошки
- основной контейнер контента
- mobile-логика sidebar

### Если нужно поменять конкретную страницу

Иду сюда:

- Vue: `resources/js/admin/pages/...`
- CSS: `resources/css/admin/_pages.scss`

Пример:

- медиатека: `resources/js/admin/pages/Media/MediaLibrary.vue`
- редактирование страницы: `resources/js/admin/pages/Pages/PageEditor.vue`

### Если нужно поменять только часть страницы

Тогда я обычно смотрю в:

- `resources/js/admin/components/...`

Например:

- `resources/js/admin/components/media/MediaLibraryModal.vue`
- `resources/js/admin/components/media/MediaSidebar.vue`
- `resources/js/admin/components/media/MediaUploader.vue`
- `resources/js/admin/components/media/MediaPickerField.vue`

## Как быстро найти нужный файл

Если я не знаю, где лежит элемент, я ищу так:

1. по route в `resources/js/admin/router.js`
2. по названию компонента
3. по тексту кнопки
4. по css-классу

Примеры:

- если мне нужен экран медиатеки, я сначала смотрю `router.js`
- если мне нужна кнопка `Сохранить`, я ищу по тексту в `resources/js/admin/pages` и `resources/js/admin/components`
- если мне нужен стиль `.media-library-page__drawer`, я иду в `resources/css/admin/_pages.scss`

## Базовые файлы, которые стоит знать

### `resources/views/admin.blade.php`

Это Blade-вход в админку.

Что важно:

- именно здесь подключаются `resources/css/app.scss` и `resources/js/admin/app.js`
- здесь есть контейнер `#admin-app`, в который монтируется Vue

Обычно верстку здесь почти не трогаю.

### `resources/js/admin/app.js`

Это точка входа Vue.

Что делает:

- создает Vue-приложение
- подключает Pinia
- подключает router
- монтирует приложение в `#admin-app`

Обычно это не место для верстки.

### `resources/js/admin/AdminApp.vue`

Очень короткий файл.

Фактически это просто:

```vue
<template>
    <router-view />
</template>
```

Обычно его не трогаю.

### `resources/js/admin/router.js`

Это главный файл, если нужно понять, какой Vue-файл отвечает за экран.

Примеры маршрутов:

- `media` -> `resources/js/admin/pages/Media/MediaLibrary.vue`
- `settings` -> `resources/js/admin/pages/Settings/SettingsIndex.vue`
- `pages` -> `resources/js/admin/pages/Pages/PagesIndex.vue`
- `page-edit` -> `resources/js/admin/pages/Pages/PageEditor.vue`

## Общие UI-компоненты

Папка:

- `resources/js/admin/components/ui`

Это базовые кирпичики интерфейса.

### `resources/js/admin/components/ui/AdminButton.vue`

Это общая кнопка.

Сама структура очень маленькая:

```vue
<template>
    <button :class="buttonClass" :type="type" :disabled="disabled">
        <slot />
    </button>
</template>
```

То есть если мне нужно менять логику или структуру кнопки, я иду сюда.

Если мне нужно менять внешний вид кнопки, я иду в:

- `resources/css/admin/_components.scss`

Там лежат классы:

- `.button-base`
- `.button-primary`
- `.button-secondary`
- `.button-danger`
- `.button-link`

Пример, как здесь обычно должна выглядеть кнопка по структуре:

```vue
<AdminButton type="button" variant="primary">
	Сохранить
</AdminButton>
```

Если нужна обычная вторичная кнопка:

```vue
<AdminButton type="button">
	Отмена
</AdminButton>
```

Если нужна опасная кнопка:

```vue
<AdminButton type="button" variant="danger">
	Удалить
</AdminButton>
```

### `resources/js/admin/components/ui/AdminPage.vue`

Это стандартная обертка страницы.

Я использую ее, когда хочу нормальную структуру экрана: eyebrow, заголовок, описание и действия справа.

Пример структуры:

```vue
<AdminPage
    eyebrow="Media"
    title="Медиатека"
    description="Управление файлами и папками"
>
	<template #actions>
		<AdminButton type="button" variant="primary">Добавить</AdminButton>
	</template>

	<section class="panel-card">
		Контент страницы
	</section>
</AdminPage>
```

### `resources/js/admin/components/ui/AdminCard.vue`

Это очень простая обертка карточки:

```vue
<template>
    <section class="panel-card">
        <slot />
    </section>
</template>
```

То есть если нужен обычный блок с бордером, фоном и внутренним отступом, удобно использовать именно его.

### `resources/js/admin/components/ui/AdminBadge.vue`

Это маленький бейдж.

Пример:

```vue
<AdminBadge>Активно</AdminBadge>
<AdminBadge :soft="true">Черновик</AdminBadge>
```

### `resources/js/admin/components/ui/AdminStatCard.vue`

Использую для KPI и карточек со статистикой.

### `resources/js/admin/components/ui/PageContentToolbar.vue`

Использую там, где нужен отдельный toolbar внутри страницы.

## Как я обычно строю страницу

Ниже нормальная простая структура без лишнего:

```vue
<template>
    <AdminPage
        eyebrow="Settings"
        title="Настройки"
        description="Основные параметры сайта"
    >
        <section class="panel-card admin-form-stack">
            <label class="admin-form-label">
                <span>Название сайта</span>
                <input class="admin-input" type="text" />
            </label>

            <div class="admin-actions-row">
                <AdminButton type="submit" variant="primary"
                    >Сохранить</AdminButton
                >
                <AdminButton type="button">Отмена</AdminButton>
            </div>
        </section>
    </AdminPage>
</template>
```

Если нужен хороший старт для нового экрана, я бы делал именно так.

## Где лежат страницы

Папка:

- `resources/js/admin/pages`

Каждая подпапка - отдельный раздел админки.

Примеры:

- `Dashboard`
- `Media`
- `Pages`
- `Users`
- `Roles`
- `Settings`
- `Plugins`
- `Themes`
- `Records`
- `Blog`

Если нужно переписать экран целиком, почти всегда я начинаю отсюда.

## Где лежат компоненты страниц

Папка:

- `resources/js/admin/components`

Основные группы:

- `ui` - общие базовые элементы
- `media` - все, что связано с медиатекой
- `custom-fields` - кастомные поля

Примеры по медиатеке:

- `MediaLibraryModal.vue` - модалка медиатеки
- `MediaSidebar.vue` - правая панель файла
- `MediaUploader.vue` - загрузка файлов
- `MediaPickerField.vue` - выбор изображения/файла внутри формы

Если страница большая, я почти всегда смотрю, какие куски вынесены именно сюда.

## Где лежат стили

Главный файл подключения стилей:

- `resources/css/app.scss`

Он подтягивает:

- `resources/css/admin/_tokens.scss`
- `resources/css/admin/_base.scss`
- `resources/css/admin/_layout.scss`
- `resources/css/admin/_components.scss`
- `resources/css/admin/_pages.scss`

### `resources/css/admin/_tokens.scss`

Это основа темы.

Здесь я меняю:

- цвета
- фон
- текст
- border
- primary
- danger
- радиусы
- отступы
- светлые и темные палитры

Если нужно быстро поменять характер всей админки, начинаю отсюда.

### `resources/css/admin/_base.scss`

Это базовые стили.

Здесь лежат:

- `body`
- типографика
- `.admin-input`
- `.admin-select`
- `.admin-textarea`
- `.admin-form-label`
- `.admin-actions-row`
- `.muted`
- `.error-text`

Если задача про поля формы и общую базу, я иду именно сюда.

### `resources/css/admin/_layout.scss`

Это каркас.

Здесь живут:

- `.admin-shell`
- `.admin-sidebar`
- `.admin-main`
- `.admin-topbar`
- `.admin-content`
- `.nav-link`

Если нужно поменять меню, верхнюю панель или поведение layout на мобильных, это главный файл.

### `resources/css/admin/_components.scss`

Это общий стиль компонентов.

Здесь лежат стили для:

- кнопок
- карточек
- бейджей
- табов
- таблиц
- модалок
- общих panel-блоков

Если задача звучит как “сделать все кнопки/модалки/карточки по-новому”, я иду сюда.

### `resources/css/admin/_pages.scss`

Это стили конкретных страниц.

Если я вижу длинные классы вроде:

- `.media-library-page__...`
- `.users-toolbar__...`

значит почти наверняка нужная правка лежит здесь.

Если задача только про один экран, это обычно главный SCSS-файл.

## Быстрые готовые ориентиры

### Хочу поменять кнопку

Смотрю:

- `resources/js/admin/components/ui/AdminButton.vue`
- `resources/css/admin/_components.scss`

### Хочу поменять инпуты, select и textarea

Смотрю:

- `resources/css/admin/_base.scss`

### Хочу поменять модалку

Смотрю:

- `resources/css/admin/_components.scss`
- потом конкретный Vue-файл модалки

### Хочу поменять левое меню и шапку админки

Смотрю:

- `resources/js/admin/layout/AdminLayout.vue`
- `resources/css/admin/_layout.scss`

### Хочу поменять одну страницу

Смотрю:

- `resources/js/admin/pages/...`
- `resources/css/admin/_pages.scss`

### Хочу поменять медиатеку

Смотрю:

- `resources/js/admin/pages/Media/MediaLibrary.vue`
- `resources/js/admin/components/media/MediaLibraryModal.vue`
- `resources/js/admin/components/media/MediaSidebar.vue`
- `resources/js/admin/components/media/MediaUploader.vue`
- `resources/css/admin/_pages.scss`

## Что лучше не трогать, если задача только по верстке

Если мне не нужно менять бизнес-логику, я стараюсь не лезть в:

- `resources/js/admin/api`
- `resources/js/admin/stores`
- `resources/js/admin/composables`
- backend вне папки `resources`

Обычно для задачи по верстке хватает:

- Vue-шаблона
- локальной логики отображения
- SCSS

## Как я бы переписывал экран безопасно

Лучший порядок такой:

1. Найти страницу в `pages`
2. Посмотреть, какие дочерние компоненты она тянет
3. Понять, какие стили у страницы в `_pages.scss`
4. Не ломать сразу весь экран
5. Менять по кускам: header, toolbar, карточки, sidebar, модалка, форма
6. После каждого куска проверять сборку

Это важно, потому что одни и те же кнопки, формы и модалки здесь используются во многих местах.

## Самая короткая шпаргалка

Если совсем коротко:

- `views` - вход в админку
- `app.js` - запуск Vue
- `router.js` - карта экранов
- `layout` - общий каркас
- `pages` - сами экраны
- `components` - переиспользуемые куски
- `_base.scss` - база форм и типографики
- `_components.scss` - общие UI-стили
- `_pages.scss` - стили конкретных страниц

Если нужно что-то быстро найти:

- по странице -> `router.js`, потом `pages`
- по кнопке -> `components/ui/AdminButton.vue` и `_components.scss`
- по форме -> `_base.scss`
- по layout -> `AdminLayout.vue` и `_layout.scss`
- по конкретному экрану -> `pages` и `_pages.scss`
