<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|instrument-serif:400,400i|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>@inertia</body>
</html>
