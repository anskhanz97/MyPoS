<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#171615">
        <link rel="manifest" href="/manifest.webmanifest">
        <title>{{ config('app.name') }}</title>
        <script src="/theme-bootstrap.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
