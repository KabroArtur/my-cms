<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 | Страница не найдена</title>
    <style>
        :root {
            --site-bg: #f6f1e7;
            --site-surface: #fffdf8;
            --site-border: #dbcfba;
            --site-text: #211b16;
            --site-muted: #74695c;
            --site-accent: #9c6234;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Georgia, 'Times New Roman', serif;
            color: var(--site-text);
            background: radial-gradient(circle at top, #efe4d2 0, var(--site-bg) 55%);
        }

        .site-404 {
            width: min(720px, 100%);
            padding: 32px;
            border: 1px solid var(--site-border);
            border-radius: 28px;
            background: var(--site-surface);
            box-shadow: 0 22px 60px rgba(33, 27, 22, 0.08);
        }

        .site-404__code {
            margin: 0 0 12px;
            color: var(--site-accent);
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-size: 13px;
            font-weight: 700;
        }

        .site-404 h1 {
            margin: 0 0 14px;
            font-size: clamp(38px, 8vw, 60px);
            line-height: 0.95;
        }

        .site-404 p {
            margin: 0 0 22px;
            color: var(--site-muted);
            font-size: 18px;
            line-height: 1.6;
        }

        .site-404 a {
            display: inline-flex;
            padding: 11px 16px;
            border-radius: 999px;
            border: 1px solid var(--site-border);
            color: var(--site-text);
            text-decoration: none;
            background: white;
        }
    </style>
</head>
<body>
    <main class="site-404">
        <p class="site-404__code">404</p>
        <h1>Страница не найдена</h1>
        <p>Для сайта используется отдельный шаблон 404. Страница, которую ты запросил, не существует или недоступна.</p>
        <a href="/">Вернуться на главную</a>
    </main>
</body>
</html>