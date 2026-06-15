<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Вход в FLΞXORA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicons/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicons/apple-touch-icon.png') }}">
     @vite(['resources/views/auth/login.css'])
</head>
<body>
    <main class="login-card">
        <div class="login-card__head">
         <h1>FL<span>Ξ</span>XORA</h1>       
        </div>

        <form class="login-form" method="post" action="{{ route('login.store') }}">
            @csrf

            <div class="login-field">
                <input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username" placeholder="Введите логин" required>
                @error('login')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="login-field">
                <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Введите пароль" required>
                @error('password')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <!-- <label class="login-remember">
                <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Запомнить меня
            </label> -->

            <button class="login-submit" type="submit">Войти</button>
        </form>
    </main>
</body>
</html>