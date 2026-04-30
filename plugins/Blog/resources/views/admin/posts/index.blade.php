<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Blog: Записи</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        .grid { display: grid; gap: 24px; grid-template-columns: 1fr 1fr; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        input, textarea, select { width: 100%; padding: 8px; margin-bottom: 8px; }
        .muted { color: #666; font-size: 12px; }
        .actions { display: flex; gap: 8px; }
    </style>
</head>
<body>
    <h1>Blog: Записи</h1>
    <p><a href="/{{ app(\App\Core\Security\Services\AdminPathManager::class)->currentPath() }}">Вернуться в админку</a></p>

    @if (session('status'))
        <p><strong>{{ session('status') }}</strong></p>
    @endif

    <div class="grid">
        <section>
            <h2>Новая запись</h2>
            <form method="post" action="{{ route('blog.admin.posts.store') }}">
                @csrf
                <input type="text" name="title" placeholder="Заголовок" required>
                <input type="text" name="slug" placeholder="slug (опционально)">
                <input type="text" name="excerpt" placeholder="Краткое описание">
                <textarea name="content" rows="8" placeholder="Контент"></textarea>
                <select name="category_id">
                    <option value="">Без категории</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="tags[]" multiple>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
                <label><input type="checkbox" name="is_published" value="1"> Опубликовать</label>
                <button type="submit">Создать</button>
            </form>
        </section>

        <section>
            <h2>Записи</h2>
            <table>
                <thead>
                    <tr>
                        <th>Запись</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>
                            <strong>{{ $post->title }}</strong>
                            <div class="muted">/{{ $post->slug }}</div>
                        </td>
                        <td>{{ $post->is_published ? 'Опубликовано' : 'Черновик' }}</td>
                        <td>
                            <div class="actions">
                                <form method="post" action="{{ route('blog.admin.posts.destroy', $post) }}">
                                    @csrf
                                    @method('delete')
                                    <button type="submit">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $posts->links() }}
        </section>
    </div>
</body>
</html>
