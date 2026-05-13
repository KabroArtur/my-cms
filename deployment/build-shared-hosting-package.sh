#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/deployment/build/shared-hosting"
PACKAGE_DIR="$BUILD_DIR/package"
STAMP="${1:-$(date +%Y%m%d-%H%M%S)}"
ZIP_PATH="$BUILD_DIR/my-cms-shared-hosting-$STAMP.zip"

require_file() {
    local path="$1"

    if [[ ! -e "$path" ]]; then
        echo "Missing required file: $path" >&2
        exit 1
    fi
}

copy_item() {
    local relative_path="$1"

    rsync -a \
        --exclude '.DS_Store' \
        --exclude '*.sqlite' \
        "$ROOT_DIR/$relative_path" "$PACKAGE_DIR/"
}

reset_runtime_dirs() {
    mkdir -p \
        "$PACKAGE_DIR/storage/media" \
        "$PACKAGE_DIR/storage/framework/cache/data" \
        "$PACKAGE_DIR/storage/framework/sessions" \
        "$PACKAGE_DIR/storage/framework/views" \
        "$PACKAGE_DIR/storage/framework/testing" \
        "$PACKAGE_DIR/storage/logs" \
        "$PACKAGE_DIR/public/build"

    find "$PACKAGE_DIR/storage/framework/cache" -mindepth 1 ! -name '.gitignore' -delete || true
    find "$PACKAGE_DIR/storage/framework/sessions" -mindepth 1 ! -name '.gitignore' -delete || true
    find "$PACKAGE_DIR/storage/framework/views" -mindepth 1 ! -name '.gitignore' -delete || true
    find "$PACKAGE_DIR/storage/framework/testing" -mindepth 1 ! -name '.gitignore' -delete || true
    find "$PACKAGE_DIR/storage/logs" -mindepth 1 ! -name '.gitignore' -delete || true
}

write_root_index() {
    cat > "$PACKAGE_DIR/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP
}

write_env_template() {
    cat > "$PACKAGE_DIR/.env.production.example" <<'ENV'
APP_NAME="My CMS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com
APP_ENFORCE_CANONICAL_URL=true
TRUSTED_PROXIES=*
SHARED_HOSTING_FLAT_PUBLIC_DISK=true

APP_LOCALE=ru
APP_FALLBACK_LOCALE=ru
APP_FAKER_LOCALE=ru_RU

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_cms
DB_USERNAME=my_cms_user
DB_PASSWORD=secret

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
ENV
}

write_deploy_note() {
    cat > "$PACKAGE_DIR/DEPLOY.md" <<'MD'
# Shared Hosting Deploy

1. Upload the contents of this archive into the domain root.
2. Copy `.env.production.example` to `.env` and fill real values.
3. Ensure `storage` and `bootstrap/cache` are writable.
4. Ensure the root `build` directory is writable: theme bundles are generated there as `/build/theme-assets/...`.
5. Import the production database dump.
6. Do not delete the root `build` directory: browser assets are loaded from `/build/...`, while Laravel reads the manifest from `public/build/manifest.json`.
7. Shared-hosting package uses `SHARED_HOSTING_FLAT_PUBLIC_DISK=true`, so uploaded media will be written directly into the web-accessible `storage` directory.
8. If SSH is available, run:

   php artisan key:generate --force
   php artisan migrate --force
   php artisan optimize

    If you want to enable JS obfuscation from CMS settings, also run:

    npm install --omit=dev

If SSH is not available, generate `APP_KEY` locally and put it into `.env`, then import a DB that already contains migrated tables.

If Node.js or javascript-obfuscator is unavailable on the server, the CMS falls back to regular JS minification and keeps the site working.
MD
}

write_installer_copy() {
    cp "$ROOT_DIR/deployment/install.php" "$BUILD_DIR/install.php"
}

require_file "$ROOT_DIR/vendor/autoload.php"
require_file "$ROOT_DIR/public/build/manifest.json"
require_file "$ROOT_DIR/public/.htaccess"

rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"
mkdir -p "$BUILD_DIR"
rm -f "$ZIP_PATH"

for item in app artisan bootstrap config database modules plugins resources routes storage themes vendor composer.json composer.lock; do
    copy_item "$item"
done

if [[ -f "$ROOT_DIR/package.json" ]]; then
    copy_item "package.json"
fi

if [[ -f "$ROOT_DIR/package-lock.json" ]]; then
    copy_item "package-lock.json"
fi

rsync -a \
    --exclude '.DS_Store' \
    --exclude 'index.php' \
    --exclude 'hot' \
    "$ROOT_DIR/public/" "$PACKAGE_DIR/"

cp "$ROOT_DIR/public/.htaccess" "$PACKAGE_DIR/.htaccess"
rsync -a "$ROOT_DIR/public/build/" "$PACKAGE_DIR/public/build/"

reset_runtime_dirs
write_root_index
write_env_template
write_deploy_note
write_installer_copy

(
    cd "$PACKAGE_DIR"
    zip -qry "$ZIP_PATH" .
)

echo "Package directory: $PACKAGE_DIR"
echo "Archive: $ZIP_PATH"
echo "Installer: $BUILD_DIR/install.php"