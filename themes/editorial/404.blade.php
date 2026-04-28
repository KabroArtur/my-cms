<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 | Страница не найдена</title>
    <style>
        :root {
            --bg: #f2eee6;
            --surface: #fffdfa;
            --border: #d7cbb9;
            --text: #241d18;
            --muted: #75685b;
            --accent: #9a4f2a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Georgia, 'Times New Roman', serif;
            background: linear-gradient(180deg, #eadfce 0, var(--bg) 100%);
            color: var(--text);
        }

        .editorial-404 {
            width: min(760px, 100%);
            padding: 36px;
            border-radius: 32px;
            border: 1px solid var(--border);
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(36, 29, 24, 0.08);
        }

        .editorial-404__code {
            margin: 0 0 14px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 12px;
            font-weight: 700;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(42px, 8vw, 64px);
            line-height: 0.95;
        }

        p {
            margin: 0 0 22px;
            color: var(--muted);
            font-size: 18px;
            line-height: 1.7;
        }

        a {
            display: inline-flex;
            padding: 12px 18px;
            border-radius: 999px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>
    <main class="editorial-404">
        <p class="editorial-404__code">404</p>
        <h1>Страница не найдена</h1>
        <p>Для сайта используется отдельный шаблон 404. Страница не существует или была скрыта.</p>
        <a href="/">Вернуться на главную</a>
    </main>
</body>
</html>