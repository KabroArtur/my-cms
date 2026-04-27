<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CMS Admin</title>
    @vite(['resources/css/app.scss', 'resources/js/admin/app.js'])
</head>
<body>
    <div id="admin-app"></div>
</body>
</html>