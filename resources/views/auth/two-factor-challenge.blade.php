<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-Factor Challenge</title>
</head>
<body>
    <main>
        <h1>Подтверждение входа</h1>

        <p>Код отправлен на {{ $user->email }}.</p>
        <p>Пока почта настроена через mail log, код можно увидеть в [storage/logs/laravel.log](/Users/arturkabro/Herd/my-cms/storage/logs/laravel.log).</p>

        @if (session('status'))
            <p><strong>{{ session('status') }}</strong></p>
        @endif

        <fieldset>
            <legend>Two-factor code</legend>

            <form method="post" action="{{ route('two-factor.store') }}">
                @csrf

                <table border="1" cellpadding="8" cellspacing="0">
                    <tbody>
                        <tr>
                            <td><label for="code">Код</label></td>
                            <td><input id="code" name="code" type="text" inputmode="numeric" value="{{ old('code') }}" autocomplete="one-time-code"></td>
                            <td>@error('code') <strong>{{ $message }}</strong> @enderror</td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <button type="submit">[ Подтвердить ]</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <form method="post" action="{{ route('two-factor.resend') }}">
                @csrf
                <button type="submit">[ Отправить код заново ]</button>
            </form>
        </fieldset>
    </main>
</body>
</html>