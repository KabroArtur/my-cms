<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $page->seo_title ?? $page->title ?? 'CMS Site' }}</title>
</head>
<body>
    <main>
        <h1>{{ $page->title ?? 'Home' }}</h1>

        <div>
            {!! $page->content ?? '' !!}
        </div>
    </main>
</body>
</html>