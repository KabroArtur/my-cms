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

Что нужно сделать после загрузки архива на хостинг:

1. Распаковать архив прямо в корень домена.
2. Скопировать `.env.production.example` в `.env`.
3. Прописать реальные `APP_URL`, `DB_*`, почту и остальные prod-настройки.
4. Убедиться, что `storage` и `bootstrap/cache` доступны на запись.
5. Для fallback-архива используется `SHARED_HOSTING_FLAT_PUBLIC_DISK=true`: это нужно, чтобы загруженные изображения были доступны по URL вида `/storage/...` без symlink.
6. Если есть SSH, выполнить:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
```

7. Если SSH нет, то:

- сгенерируйте `APP_KEY` локально и вставьте его в `.env`
- импортируйте уже подготовленную боевую базу данных
- проверьте права на запись для `storage` и `bootstrap/cache`

Что важно понимать:

- один только архив не создаст базу и не заполнит `.env` автоматически
- если у провайдера можно выбрать document root, лучше не использовать этот fallback-пакет, а деплоить обычную структуру Laravel с веб-корнем в `public`