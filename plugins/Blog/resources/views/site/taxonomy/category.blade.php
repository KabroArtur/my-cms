<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Категория {{ $category->name }}</title>
</head>
<body>
    <h1>{{ $section->name }} / Категория: {{ $category->name }}</h1>
    @forelse ($posts as $post)
        <article>
            <h2><a href="/{{ $section->slug }}/{{ $post->slug }}">{{ $post->title }}</a></h2>
        </article>
    @empty
        <p>Постов нет.</p>
    @endforelse

    {{ $posts->links() }}
</body>
</html>
