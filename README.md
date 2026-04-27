# My CMS

Проект представляет собой CMS на Laravel + Vue + Vite.

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
APP_URL=http://my-cms.test

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
http://my-cms.test
```

Страница входа:

```text
http://my-cms.test/login
```

Админка:

```text
http://my-cms.test/admin/pages
```

## Данные администратора

После `php artisan db:seed` создается базовый администратор:

- логин: `admin`
- пароль: `admin`

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

## Доступные полезные команды

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
