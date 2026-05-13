<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {!! theme_head($page) !!}
</head>
<body>
    {!! component('components/header') !!}
    <main>
        @if (template() !== 'default')
            {!! template_content() !!}
        @else
            {!! content() !!}
        @endif
    </main>
    {!! component('components/footer') !!}
    {!! component('components/modals') !!}
    {!! footer() !!}
</body>
</html>
