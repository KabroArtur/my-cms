<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход в CMS</title>
    <style>
        :root {
            color-scheme: light;
            --login-bg: #f2f4f8;
            --login-surface: #ffffff;
            --login-border: #d6dce8;
            --login-text: #162033;
            --login-muted: #5e6b83;
            --login-accent: #2448b8;
            --login-danger: #b3261e;
            --login-shadow: 0 20px 60px rgba(22, 32, 51, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "IBM Plex Sans", "Segoe UI", Tahoma, sans-serif;
            color: var(--login-text);
            background:
                radial-gradient(circle at 15% 10%, rgba(36, 72, 184, 0.12), transparent 32%),
                radial-gradient(circle at 85% 90%, rgba(36, 72, 184, 0.08), transparent 28%),
                var(--login-bg);
            display: grid;
            place-items: center;
            padding: 24px 12px;
        }

        .login-card {
            width: min(460px, 100%);
            border: 1px solid var(--login-border);
            border-radius: 20px;
            background: var(--login-surface);
            box-shadow: var(--login-shadow);
            overflow: hidden;
        }

        .login-card__head {
            padding: 24px 24px 18px;
            border-bottom: 1px solid var(--login-border);
            background: linear-gradient(180deg, rgba(36, 72, 184, 0.08), rgba(36, 72, 184, 0));
        }

        .login-card__head p {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--login-muted);
        }

        .login-card__head h1 {
            margin: 0;
            font-size: 30px;
            line-height: 1.1;
        }

        .login-form {
            display: grid;
            gap: 16px;
            padding: 20px 24px 24px;
        }

        .login-field {
            display: grid;
            gap: 8px;
        }

        .login-field label {
            font-size: 14px;
            font-weight: 600;
        }

        .login-field input[type="text"],
        .login-field input[type="password"] {
            width: 100%;
            border: 1px solid var(--login-border);
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 15px;
            color: var(--login-text);
            background: #fff;
        }

        .login-field input[type="text"]:focus,
        .login-field input[type="password"]:focus {
            outline: 2px solid rgba(36, 72, 184, 0.18);
            border-color: var(--login-accent);
        }

        .login-remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--login-muted);
        }

        .login-error {
            margin: 0;
            color: var(--login-danger);
            font-size: 13px;
            font-weight: 600;
        }

        .login-submit {
            border: 1px solid var(--login-accent);
            border-radius: 10px;
            background: var(--login-accent);
            color: #fff;
            padding: 11px 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .login-submit:hover {
            filter: brightness(0.95);
        }
    </style>
</head>
<body>
    <main class="login-card">
        <header class="login-card__head">
            <p>CMS</p>
            <h1>Вход в админ-панель</h1>
        </header>

        <form class="login-form" method="post" action="{{ route('login.store') }}">
            @csrf

            <div class="login-field">
                <label for="login">Логин</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" required>
                @error('login')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="login-field">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <label class="login-remember">
                <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Запомнить меня
            </label>

            <button class="login-submit" type="submit">Войти</button>
        </form>
    </main>
</body>
</html>