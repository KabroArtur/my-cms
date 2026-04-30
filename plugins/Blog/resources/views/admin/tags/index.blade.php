<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Blog: Теги</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        input { width: 100%; padding: 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Blog: Теги</h1>
    @if (session('status'))
        <p><strong>{{ session('status') }}</strong></p>
    @endif

    <h2>Новый тег</h2>
    <form method="post" action="{{ route('blog.admin.tags.store') }}">
        @csrf
        <input type="text" name="name" placeholder="Название" required>
        <input type="text" name="slug" placeholder="slug (опционально)">
        <button type="submit">Создать</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Название</th>
                <th>Slug</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($tags as $tag)
            <tr>
                <td>{{ $tag->name }}</td>
                <td>{{ $tag->slug }}</td>
                <td>
                    <form method="post" action="{{ route('blog.admin.tags.destroy', $tag) }}">
                        @csrf
                        @method('delete')
                        <button type="submit">Удалить</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
