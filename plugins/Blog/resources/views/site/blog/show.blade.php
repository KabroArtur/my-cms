<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $post->title }}</title>
    <style>
        body { font-family: sans-serif; margin: 24px; max-width: 900px; }
    </style>
</head>
<body>
    <p><a href="/{{ $section->slug }}">К разделу {{ $section->name }}</a></p>
    <h1>{{ $post->title }}</h1>
    @if ($post->category)
        <p>Категория: <a href="/{{ $section->slug }}/category/{{ $post->category->slug }}">{{ $post->category->name }}</a></p>
    @endif
    <article>{!! nl2br(e($post->content ?? '')) !!}</article>
</body>
</html>
