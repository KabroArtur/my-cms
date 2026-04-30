<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Blog: Категории</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        input, textarea { width: 100%; padding: 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Blog: Категории</h1>
    @if (session('status'))
        <p><strong>{{ session('status') }}</strong></p>
    @endif

    <h2>Новая категория</h2>
    <form method="post" action="{{ route('blog.admin.categories.store') }}">
        @csrf
        <input type="text" name="name" placeholder="Название" required>
        <input type="text" name="slug" placeholder="slug (опционально)">
        <textarea name="description" rows="4" placeholder="Описание"></textarea>
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
        @foreach ($categories as $category)
            <tr>
                <td>{{ $category->name }}</td>
                <td>{{ $category->slug }}</td>
                <td>
                    <form method="post" action="{{ route('blog.admin.categories.destroy', $category) }}">
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
