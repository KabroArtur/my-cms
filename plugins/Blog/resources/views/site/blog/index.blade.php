<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Блог</title>
    <style>
        body { font-family: sans-serif; margin: 24px; max-width: 900px; }
        article { border-bottom: 1px solid #ddd; padding: 16px 0; }
    </style>
</head>
<body>
    <h1>{{ $section->name }}</h1>
    @forelse ($posts as $post)
        <article>
            <h2><a href="/{{ $section->slug }}/{{ $post->slug }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)
                <p>{{ $post->excerpt }}</p>
            @endif
        </article>
    @empty
        <p>Постов пока нет.</p>
    @endforelse

    {{ $posts->links() }}
</body>
</html>
