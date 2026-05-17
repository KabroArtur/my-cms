<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
    <style>
        .theme-page-content {
            width: min(1100px, calc(100% - 32px));
            margin: 32px auto;
        }

        .theme-page-content video {
            display: block;
            width: 100%;
            max-width: 100%;
            margin: 0 0 20px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.14);
            background: #0f172a;
        }

        .theme-page-content a[download] {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 600;
        }

        .theme-page-content a[download]::before {
            content: "Файл";
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.7;
        }
    </style>
</head>
<body {!! attr(['class' => 'zerno-body']) !!}>

    <main class="zerno-shell">
        {!! component('components/header') !!}

        @if (template() !== 'default')
            {!! template_content() !!}
        @else
            <article class="theme-page-content">
                {!! content() !!}
            </article>
        @endif

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>
