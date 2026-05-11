<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
</head>
<body {!! attr(['class' => 'zerno-body']) !!}>

    <main class="zerno-shell">
        {!! component('components/header') !!}

        @if (template() !== 'default')
            {!! template_content() !!}
        @else
            {!! content() !!}
        @endif

        {!! component('components/footer') !!}
    </main>

    {!! footer() !!}
</body>
</html>
