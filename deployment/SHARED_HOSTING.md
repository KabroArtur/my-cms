# Прод-архив для shared hosting

Нормальный вариант для Laravel такой: корень домена должен смотреть в папку `public`. Если хостинг это умеет, лучше использовать именно этот режим.

Если хостинг примитивный и домен смотрит только в `public_html` или в корень архива, используйте подготовку special-пакета:

```bash
./deployment/build-shared-hosting-package.sh
```

Что делает скрипт:

- собирает папку `deployment/build/shared-hosting/package`
- переносит содержимое `public` в корень deploy-пакета
- генерирует корневой `index.php` с правильными путями для Laravel
- копирует `.htaccess` в корень пакета
- чистит временные runtime-файлы из `storage`
- создает архив `deployment/build/shared-hosting/my-cms-shared-hosting-<timestamp>.zip`

Что уже должно быть готово до сборки:

- установлен `vendor`
- выполнен `npm run build`
- проект проверен локально
- если планируете включать JS obfuscation через настройки сайта, на сервере должен быть доступен Node.js и установлен `javascript-obfuscator`

Есть теперь два сценария установки.

## Вариант 1. Через install.php

1. Соберите архив локально:

```bash
./deployment/build-shared-hosting-package.sh
```

2. Возьмите два артефакта из папки сборки:

- `deployment/build/shared-hosting/install.php`
- `deployment/build/shared-hosting/my-cms-shared-hosting-<timestamp>.zip`

3. Загрузите на сайт сначала только `install.php`.
4. Разместите ZIP-архив в доступном месте по прямому URL или загрузите его рядом с `install.php`.
5. Откройте `https://ваш-сайт/install.php` и заполните:

- URL архива или имя локального ZIP-файла
- `APP_URL`
- параметры БД
- данные администратора
- при необходимости SMTP-настройки

6. Инсталлятор:

- скачает архив
- распакует CMS в текущую директорию
- создаст `.env`
- сгенерирует `APP_KEY`
- выполнит `migrate`, `db:seed`, `optimize`
- создаст или обновит admin-пользователя с указанным паролем

7. После успешной установки удалите `install.php` с сервера.

## Вариант 2. Ручной деплой архива

Что нужно сделать после загрузки архива на хостинг:

1. Распаковать архив прямо в корень домена.
2. Скопировать `.env.production.example` в `.env`.
3. Прописать реальные `APP_URL`, `DB_*`, почту и остальные prod-настройки.
4. Убедиться, что `storage` и `bootstrap/cache` доступны на запись.
5. Убедиться, что корневая папка `build` тоже доступна на запись: в shared-hosting режиме theme assets генерируются в `/build/theme-assets/...`.
6. Для fallback-архива используется `SHARED_HOSTING_FLAT_PUBLIC_DISK=true`: это нужно, чтобы загруженные изображения были доступны по URL вида `/storage/...` без symlink.
7. Если есть SSH, выполнить:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
npm install --omit=dev
```

8. Если SSH нет, то:

- сгенерируйте `APP_KEY` локально и вставьте его в `.env`
- импортируйте уже подготовленную боевую базу данных
- проверьте права на запись для `storage` и `bootstrap/cache`

Что важно понимать:

- один только архив не создаст базу и не заполнит `.env` автоматически
- `install.php` закрывает этот пробел и подходит для shared hosting, где удобнее стартовать с браузера
- если на хостинге нет Node.js или не установлен `javascript-obfuscator`, переключатель JS obfuscation не уронит сайт: CMS автоматически откатится к обычному minify JS
- если у провайдера можно выбрать document root, лучше не использовать этот fallback-пакет, а деплоить обычную структуру Laravel с веб-корнем в `public`