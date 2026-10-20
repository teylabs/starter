<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- The saved appearance, before first paint: no flash of the wrong theme on load. --}}
    <script>(function () { try { var a = localStorage.getItem('appearance') || 'system'; document.documentElement.classList.toggle('dark', a === 'dark' || (a === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)); } catch (e) {} })();</script>
    <x-kit::brand />
    <x-kit::fonts />
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>@inertia</body>
</html>
