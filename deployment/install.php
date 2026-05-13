<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = __DIR__;
$lockFile = $rootDir.'/.install.lock';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$messages = [];
$errors = [];
$installResult = null;

function installer_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function installer_base_url(): string
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/install.php';
    $path = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $scheme.'://'.$host.($path !== '' ? $path : '');
}

function installer_random_string(int $length = 18): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
    $maxIndex = strlen($alphabet) - 1;
    $result = '';

    for ($index = 0; $index < $length; $index++) {
        $result .= $alphabet[random_int(0, $maxIndex)];
    }

    return $result;
}

function installer_env_value(mixed $value): string
{
    $string = (string) $value;

    if ($string === '') {
        return '';
    }

    if (preg_match("/[\\s#\"']/", $string) === 1) {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $string).'"';
    }

    return $string;
}

function installer_put_env(string $content, string $key, mixed $value): string
{
    $line = $key.'='.installer_env_value($value);
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    if (preg_match($pattern, $content) === 1) {
        return (string) preg_replace($pattern, $line, $content, 1);
    }

    return rtrim($content).PHP_EOL.$line.PHP_EOL;
}

function installer_download_archive(string $source, string $target): void
{
    if (filter_var($source, FILTER_VALIDATE_URL)) {
        if (function_exists('curl_init')) {
            $handle = curl_init($source);

            if ($handle === false) {
                throw new RuntimeException('Не удалось инициализировать cURL.');
            }

            $file = fopen($target, 'wb');

            if ($file === false) {
                curl_close($handle);

                throw new RuntimeException('Не удалось создать временный файл для архива.');
            }

            curl_setopt_array($handle, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_USERAGENT => 'MyCMS Installer/1.0',
            ]);

            $result = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            fclose($file);
            curl_close($handle);

            if ($result === false || $status >= 400) {
                @unlink($target);

                throw new RuntimeException('Не удалось скачать архив: '.($error !== '' ? $error : 'HTTP '.$status));
            }

            return;
        }

        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'timeout' => 600,
                'user_agent' => 'MyCMS Installer/1.0',
            ],
            'https' => [
                'follow_location' => 1,
                'timeout' => 600,
                'user_agent' => 'MyCMS Installer/1.0',
            ],
        ]);

        $contents = @file_get_contents($source, false, $context);

        if ($contents === false || file_put_contents($target, $contents) === false) {
            throw new RuntimeException('Не удалось скачать архив по указанному URL.');
        }

        return;
    }

    $localPath = __DIR__.'/'.ltrim($source, '/');

    if (! is_file($localPath)) {
        throw new RuntimeException('Архив не найден: '.$source);
    }

    if (! copy($localPath, $target)) {
        throw new RuntimeException('Не удалось скопировать локальный архив.');
    }
}

function installer_extract_archive(string $archivePath, string $destination): void
{
    if (! class_exists('ZipArchive')) {
        throw new RuntimeException('Расширение ZipArchive недоступно на сервере.');
    }

    $zip = new ZipArchive();
    $openResult = $zip->open($archivePath);

    if ($openResult !== true) {
        throw new RuntimeException('Не удалось открыть ZIP-архив. Код: '.$openResult);
    }

    if (! $zip->extractTo($destination)) {
        $zip->close();

        throw new RuntimeException('Не удалось распаковать архив в целевую директорию.');
    }

    $zip->close();
}

function installer_run_artisan(string $rootDir, string $command, array $parameters = []): array
{
    require_once $rootDir.'/vendor/autoload.php';

    $app = require $rootDir.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $exitCode = $kernel->call($command, $parameters);
    $output = $kernel->output();

    return [$exitCode, $output];
}

$defaults = [
    'archive_url' => 'https://github.com/KabroArtur/my-cms/releases/download/v1.0.1/my-cms-v1.0.2.zip',
    'app_name' => 'My CMS',
    'app_url' => installer_base_url(),
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => 'my_cms',
    'db_username' => 'my_cms_user',
    'db_password' => '',
    'admin_name' => 'Administrator',
    'admin_username' => 'admin',
    'admin_email' => 'admin@example.com',
    'admin_password' => installer_random_string(16),
    'mail_host' => '127.0.0.1',
    'mail_port' => '587',
    'mail_username' => '',
    'mail_password' => '',
    'mail_from_address' => 'hello@example.com',
];

$input = $defaults;

if ($isPost) {
    foreach ($defaults as $key => $value) {
        $input[$key] = trim((string) ($_POST[$key] ?? $value));
    }

    if (is_file($lockFile)) {
        $errors[] = 'Инсталлятор уже выполнялся в этой директории. Удалите .install.lock вручную, если нужен повторный запуск.';
    }

    foreach (['archive_url', 'app_url', 'db_host', 'db_database', 'db_username', 'admin_username', 'admin_email', 'admin_password'] as $requiredField) {
        if ($input[$requiredField] === '') {
            $errors[] = 'Заполните обязательное поле: '.$requiredField;
        }
    }

    if ($input['archive_url'] !== '' && ! filter_var($input['archive_url'], FILTER_VALIDATE_URL) && ! is_file($rootDir.'/'.ltrim($input['archive_url'], '/'))) {
        $errors[] = 'Укажите корректный URL архива или имя локального ZIP-файла рядом с install.php.';
    }

    if ($input['app_url'] !== '' && filter_var($input['app_url'], FILTER_VALIDATE_URL) === false) {
        $errors[] = 'APP_URL должен быть полноценным URL, например https://example.com.';
    }

    if ($input['admin_email'] !== '' && filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Укажите корректный email администратора.';
    }

    if ($errors === []) {
        $tempArchive = sys_get_temp_dir().'/my-cms-install-'.bin2hex(random_bytes(8)).'.zip';
        $commandLog = [];

        try {
            installer_download_archive($input['archive_url'], $tempArchive);
            $messages[] = 'Архив скачан.';

            installer_extract_archive($tempArchive, $rootDir);
            @unlink($tempArchive);
            $messages[] = 'Архив распакован.';

            $envTemplatePath = is_file($rootDir.'/.env.production.example')
                ? $rootDir.'/.env.production.example'
                : $rootDir.'/.env.example';

            if (! is_file($envTemplatePath)) {
                throw new RuntimeException('Не найден шаблон .env.production.example или .env.example в распакованном архиве.');
            }

            $envContent = (string) file_get_contents($envTemplatePath);
            $appKey = 'base64:'.base64_encode(random_bytes(32));
            $envValues = [
                'APP_NAME' => $input['app_name'],
                'APP_ENV' => 'production',
                'APP_KEY' => $appKey,
                'APP_DEBUG' => 'false',
                'APP_URL' => rtrim($input['app_url'], '/'),
                'DB_HOST' => $input['db_host'],
                'DB_PORT' => $input['db_port'] !== '' ? $input['db_port'] : '3306',
                'DB_DATABASE' => $input['db_database'],
                'DB_USERNAME' => $input['db_username'],
                'DB_PASSWORD' => $input['db_password'],
                'MAIL_HOST' => $input['mail_host'],
                'MAIL_PORT' => $input['mail_port'] !== '' ? $input['mail_port'] : '587',
                'MAIL_USERNAME' => $input['mail_username'],
                'MAIL_PASSWORD' => $input['mail_password'],
                'MAIL_FROM_ADDRESS' => $input['mail_from_address'],
                'ADMIN_INITIAL_PASSWORD' => $input['admin_password'],
                'SHARED_HOSTING_FLAT_PUBLIC_DISK' => 'true',
            ];

            foreach ($envValues as $key => $value) {
                $envContent = installer_put_env($envContent, $key, $value);
            }

            if (file_put_contents($rootDir.'/.env', $envContent) === false) {
                throw new RuntimeException('Не удалось записать файл .env.');
            }

            $messages[] = 'Файл .env создан.';

            foreach ([
                $rootDir.'/storage',
                $rootDir.'/storage/framework',
                $rootDir.'/storage/framework/cache',
                $rootDir.'/storage/framework/cache/data',
                $rootDir.'/storage/framework/sessions',
                $rootDir.'/storage/framework/views',
                $rootDir.'/storage/logs',
                $rootDir.'/bootstrap/cache',
                $rootDir.'/public/build',
                $rootDir.'/build',
            ] as $directory) {
                if (! is_dir($directory)) {
                    @mkdir($directory, 0775, true);
                }
            }

            [$status, $output] = installer_run_artisan($rootDir, 'migrate', ['--force' => true]);
            $commandLog[] = ['command' => 'php artisan migrate --force', 'status' => $status, 'output' => $output];

            if ($status !== 0) {
                throw new RuntimeException('Команда migrate завершилась с ошибкой.');
            }

            [$status, $output] = installer_run_artisan($rootDir, 'db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]);
            $commandLog[] = ['command' => 'php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force', 'status' => $status, 'output' => $output];

            if ($status !== 0) {
                throw new RuntimeException('Команда db:seed завершилась с ошибкой.');
            }

            require_once $rootDir.'/vendor/autoload.php';
            $app = require $rootDir.'/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            $user = App\Models\User::query()->firstOrNew([
                'username' => $input['admin_username'],
            ]);
            $user->forceFill([
                'name' => $input['admin_name'],
                'email' => $input['admin_email'],
                'password' => $input['admin_password'],
                'two_factor_channel' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_enabled_at' => null,
            ])->save();

            $role = App\Core\Roles\Models\Role::query()->where('slug', 'admin')->first();

            if ($role !== null) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }

            [$status, $output] = installer_run_artisan($rootDir, 'optimize:clear');
            $commandLog[] = ['command' => 'php artisan optimize:clear', 'status' => $status, 'output' => $output];

            [$status, $output] = installer_run_artisan($rootDir, 'optimize');
            $commandLog[] = ['command' => 'php artisan optimize', 'status' => $status, 'output' => $output];

            file_put_contents($lockFile, json_encode([
                'installed_at' => date(DATE_ATOM),
                'app_url' => $input['app_url'],
                'admin_username' => $input['admin_username'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $installResult = [
                'app_url' => rtrim($input['app_url'], '/'),
                'admin_username' => $input['admin_username'],
                'admin_email' => $input['admin_email'],
                'admin_password' => $input['admin_password'],
                'commands' => $commandLog,
            ];

            $messages[] = 'CMS установлена. После проверки удалите install.php с сервера.';
        } catch (Throwable $exception) {
            @unlink($tempArchive ?? '');
            $errors[] = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My CMS Installer</title>
    <style>
        body {
            margin: 0;
            font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f6fb;
            color: #1f2937;
        }
        .wrap {
            max-width: 920px;
            margin: 0 auto;
            padding: 32px 16px 56px;
        }
        .card {
            background: #fff;
            border: 1px solid #d8dfeb;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        h1, h2, h3, p {
            margin-top: 0;
        }
        .grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .grid--full {
            grid-column: 1 / -1;
        }
        label {
            display: grid;
            gap: 6px;
            font-weight: 600;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #c9d4e5;
            font: inherit;
        }
        button {
            border: 0;
            border-radius: 12px;
            padding: 12px 18px;
            background: #1d4ed8;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .muted {
            color: #64748b;
            font-size: 14px;
        }
        .messages,
        .errors,
        .summary {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 14px;
        }
        .messages {
            background: #eefbf3;
            border: 1px solid #b7e4c7;
        }
        .errors {
            background: #fff1f2;
            border: 1px solid #fecdd3;
        }
        .summary {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .stack {
            display: grid;
            gap: 10px;
        }
        pre {
            margin: 0;
            padding: 12px;
            overflow: auto;
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
        }
        @media (max-width: 720px) {
            .grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Установка My CMS</h1>
            <p class="muted">Загрузите сюда только этот файл, откройте его в браузере, укажите URL готового ZIP-архива и параметры сайта. Инсталлятор скачает архив, распакует его, создаст .env, выполнит миграции и подготовит admin-доступ.</p>

            <?php if ($messages !== []): ?>
                <div class="messages stack">
                    <?php foreach ($messages as $message): ?>
                        <div><?= installer_h($message) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="errors stack">
                    <?php foreach ($errors as $error): ?>
                        <div><?= installer_h($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($installResult !== null): ?>
                <div class="summary stack">
                    <h2>Установка завершена</h2>
                    <div>Сайт: <strong><?= installer_h($installResult['app_url']) ?></strong></div>
                    <div>Логин: <strong><?= installer_h($installResult['admin_username']) ?></strong></div>
                    <div>Email: <strong><?= installer_h($installResult['admin_email']) ?></strong></div>
                    <div>Пароль: <strong><?= installer_h($installResult['admin_password']) ?></strong></div>
                    <div class="muted">После проверки входа удалите install.php и сохраните эти данные в безопасном месте.</div>
                    <?php foreach ($installResult['commands'] as $command): ?>
                        <div>
                            <strong><?= installer_h($command['command']) ?></strong>
                            <pre><?= installer_h($command['output']) ?></pre>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="grid">
                <label class="grid--full">
                    <span>URL архива или локальное имя ZIP-файла</span>
                    <input name="archive_url" value="<?= installer_h($input['archive_url']) ?>" placeholder="https://example.com/my-cms-shared-hosting.zip или my-cms-shared-hosting.zip" required>
                </label>

                <label>
                    <span>Название сайта</span>
                    <input name="app_name" value="<?= installer_h($input['app_name']) ?>">
                </label>

                <label>
                    <span>APP_URL</span>
                    <input name="app_url" value="<?= installer_h($input['app_url']) ?>" required>
                </label>

                <label>
                    <span>DB host</span>
                    <input name="db_host" value="<?= installer_h($input['db_host']) ?>" required>
                </label>

                <label>
                    <span>DB port</span>
                    <input name="db_port" value="<?= installer_h($input['db_port']) ?>">
                </label>

                <label>
                    <span>DB name</span>
                    <input name="db_database" value="<?= installer_h($input['db_database']) ?>" required>
                </label>

                <label>
                    <span>DB user</span>
                    <input name="db_username" value="<?= installer_h($input['db_username']) ?>" required>
                </label>

                <label>
                    <span>DB password</span>
                    <input name="db_password" value="<?= installer_h($input['db_password']) ?>">
                </label>

                <label>
                    <span>Имя администратора</span>
                    <input name="admin_name" value="<?= installer_h($input['admin_name']) ?>">
                </label>

                <label>
                    <span>Логин администратора</span>
                    <input name="admin_username" value="<?= installer_h($input['admin_username']) ?>" required>
                </label>

                <label>
                    <span>Email администратора</span>
                    <input name="admin_email" value="<?= installer_h($input['admin_email']) ?>" required>
                </label>

                <label>
                    <span>Пароль администратора</span>
                    <input name="admin_password" value="<?= installer_h($input['admin_password']) ?>" required>
                </label>

                <label>
                    <span>SMTP host</span>
                    <input name="mail_host" value="<?= installer_h($input['mail_host']) ?>">
                </label>

                <label>
                    <span>SMTP port</span>
                    <input name="mail_port" value="<?= installer_h($input['mail_port']) ?>">
                </label>

                <label>
                    <span>SMTP user</span>
                    <input name="mail_username" value="<?= installer_h($input['mail_username']) ?>">
                </label>

                <label>
                    <span>SMTP password</span>
                    <input name="mail_password" value="<?= installer_h($input['mail_password']) ?>">
                </label>

                <label class="grid--full">
                    <span>MAIL_FROM_ADDRESS</span>
                    <input name="mail_from_address" value="<?= installer_h($input['mail_from_address']) ?>">
                </label>

                <div class="grid--full stack">
                    <button type="submit">Скачать архив и установить CMS</button>
                    <p class="muted">Инсталлятор создаёт APP_KEY автоматически, включает production-режим, выполняет migrate/seed и настраивает admin-пользователя с указанным паролем.</p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>