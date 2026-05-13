# My CMS

Проект представляет собой CMS на Laravel + Vue + Vite.

Frontend-слой админки теперь использует единый SCSS entry и централизованную структуру стилей.

Сейчас в проекте уже есть:

- базовый Core-слой CMS;
- модуль Pages с созданием, редактированием и удалением страниц;
- роли и разрешения;
- простая авторизация в административную зону;
- подготовленная инфраструктура под 2FA, которая сейчас выключена.

## Что нужно установить

Для локального запуска нужны:

- PHP 8.3+;
- Composer;
- Node.js 20+;
- npm;
- Sass runtime для Vite устанавливается через `npm install` автоматически;
- MySQL;
- Git;
- Herd или другая локальная среда для Laravel.

## Как установить Git

### macOS

Если установлен Homebrew:

```bash
brew install git
```

Проверка:

```bash
git --version
```

Если Git не установлен, macOS также может предложить установить Command Line Tools автоматически.

## Первый запуск проекта

### 1. Клонировать проект

```bash
git clone <repository-url>
cd my-cms
```

### 2. Установить PHP-зависимости

```bash
composer install
```

### 3. Установить frontend-зависимости

```bash
npm install
```

После установки будут доступны зависимости для Vue, Vite, Tailwind и SCSS-сборки.

### 4. Создать `.env`

Если файла нет:

```bash
cp .env.example .env
```

### 5. Сгенерировать ключ приложения

```bash
php artisan key:generate
```

### 6. Настроить базу данных

Пример настроек в `.env`:

```env
APP_NAME="My CMS"
APP_URL=https://my-cms.test
APP_ENFORCE_CANONICAL_URL=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_cms
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Применить миграции и сиды

```bash
php artisan migrate
php artisan db:seed
```

### 8. Запустить frontend

```bash
npm run dev
```

### 9. Открыть проект

Если используется Herd:

```text
https://my-cms.test
```

Страница входа:

```text
https://my-cms.test/admin/login
```

Админка:

```text
https://my-cms.test/admin/pages
```

## Данные администратора

После `php artisan db:seed` создается базовый администратор:

- логин: `admin`
- пароль: `admin`

Если нужен другой стартовый пароль, задайте `ADMIN_INITIAL_PASSWORD` в `.env` до первого `php artisan db:seed`.

## Как запускать проект дальше

### Backend

Если проект уже обслуживается через Herd, отдельный `php artisan serve` не нужен.

### Frontend

Для разработки:

```bash
npm run dev
```

Для production-сборки:

```bash
npm run build
```

Для проекта используется единый entry-файл стилей:

```text
resources/css/app.scss
```

SCSS-слой админки разложен по partial-файлам:

```text
resources/css/admin/_tokens.scss
resources/css/admin/_base.scss
resources/css/admin/_layout.scss
resources/css/admin/_components.scss
resources/css/admin/_pages.scss
```

Во Vue-компонентах админки локальные `style scoped` не используются: стили держатся централизованно в SCSS-файлах.

## Доступные полезные команды

Настройки сайта доступны в административной панели в разделе Settings. Сейчас там можно управлять названием сайта, favicon, форматами даты и времени, главной страницей, активной темой сайта, размером публичной обложки, размером вставки картинок в редактор и палитрой CMS.

## Дополнительные поля

Система дополнительных полей теперь работает по модели, близкой к ACF: поля собираются в наборы, наборы показываются на страницах по location rules, а значения доступны и в админке, и в теме через единый runtime-слой.

### Где настраивать

- В админке откройте Settings -> Структура контента.
- Создайте набор полей, задайте name и key.
- Настройте правила показа набора: по шаблону страницы, конкретным страницам, главной, slug или path.
- Добавьте поля через визуальный редактор схемы. Raw JSON для settings больше не нужен.

### Какие типы полей поддерживаются

- text
- textarea
- editor
- image
- file
- gallery
- number
- checkbox
- switch
- toggle
- select
- radio
- color
- date
- url
- email
- group
- repeater

### Как это работает на странице

- В редакторе страницы CMS автоматически подгружает только те наборы, которые подходят по location rules.
- Значения сохраняются без смены схемы БД: используются существующие таблицы additional_field_groups, additional_fields и additional_field_values.
- Старые значения не ломаются: система по-прежнему хранит значения по entity_type, entity_id и field_key.

### Что важно учитывать

- key поля должен быть уникальным и состоять только из латиницы, цифр и underscore.
- Для select и radio нужно явно задавать options.
- Для group и repeater вложенные поля также валидируются на backend.
- Если значение пустое или невалидное, CMS возвращает валидационную ошибку вместо падения экрана.

### Доступ из темы

В шаблонах используйте ThemeRuntime через объект cms:

```php
@php($heroTitle = $cms->customField('hero_title'))
@php($allFields = $cms->customFields())
@php($heroGroup = $cms->group('hero_group'))
```

Для image и file полей runtime принимает как id из медиатеки, так и сохраненный URL или объект значения.

```php
@php($heroImage = $cms->customField('hero_image'))
<img src="{{ $cms->imageUrlFromValue($heroImage) }}" alt="{{ $cms->imageAltFromValue($heroImage, 'Hero') }}">
```

Очистить конфиг:

```bash
php artisan config:clear
```

Показать подключенные Core-модули:

```bash
php artisan cms:core
```

Проверить маршруты:

```bash
php artisan route:list
```

Регенерировать thumb, medium и large для уже загруженных изображений:

```bash
php artisan media:regenerate-variants
```

Проверить сборку frontend:

```bash
npm run build
```

## Как работать с Git

### Первый раз настроить имя и почту

```bash
git config --global user.name "Your Name"
git config --global user.email "you@example.com"
```

Проверка:

```bash
git config --global --list
```

### Посмотреть изменения

```bash
git status
git diff
```

### Добавить изменения в коммит

```bash
git add .
```

Или добавить конкретный файл:

```bash
git add README.md
```

### Создать коммит

```bash
git commit -m "update pages module"
```

### Отправить обновления в GitHub

Если ветка уже привязана:

```bash
git push
```

Если это первый push для ветки:

```bash
git push -u origin main
```

Если ветка называется не `main`, нужно подставить свое имя ветки.

### Быстрый сценарий заливки обновлений

```bash
git status
git add .
git commit -m "update cms"
git push
```

## Если после `git clone` проект не запускается

Нужно проверить:

- установлен ли Composer;
- установлен ли Node.js;
- создан ли `.env`;
- создана ли база `my_cms`;
- выполнены ли `php artisan migrate` и `php artisan db:seed`;
- запущен ли `npm run dev`.

## Состояние авторизации

Сейчас в проекте работает:

- вход по логину и паролю;
- роли и разрешения;
- защита административной зоны.

2FA в проекте подготовлен, но сейчас глобально отключен через конфиг.

## Что важно помнить

- `npm start` в этом проекте не используется;
- для frontend нужен `npm run dev`;
- для сборки нужен `npm run build`;
- для входа в админку нужен `admin / admin`;
- пароль хранится в базе в виде хеша, а не в открытом виде.
