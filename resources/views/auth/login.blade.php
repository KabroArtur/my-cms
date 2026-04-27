<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>
</head>
<body>
    <main>
        <h1>Вход</h1>

        <fieldset>
            <legend>Авторизация</legend>

            <form method="post" action="{{ route('login.store') }}">
                @csrf

                <table border="1" cellpadding="8" cellspacing="0">
                    <tbody>
                        <tr>
                            <td><label for="login">Логин</label></td>
                            <td><input id="login" name="login" type="text" value="{{ old('login', 'admin') }}" autocomplete="username"></td>
                            <td>@error('login') <strong>{{ $message }}</strong> @enderror</td>
                        </tr>
                        <tr>
                            <td><label for="password">Пароль</label></td>
                            <td><input id="password" name="password" type="password" value="admin" autocomplete="current-password"></td>
                            <td>@error('password') <strong>{{ $message }}</strong> @enderror</td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <label>
                                    <input name="remember" type="checkbox" value="1"> Запомнить
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <button type="submit">[ Войти ]</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </fieldset>
    </main>
</body>
</html>