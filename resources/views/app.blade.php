<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- The saved appearance, before first paint: no flash of the wrong theme on load. --}}
    <script>(function () { try { var a = localStorage.getItem('appearance') || 'system'; document.documentElement.classList.toggle('dark', a === 'dark' || (a === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)); } catch (e) {} })();</script>
    {{-- The title is the server's: the page's breadcrumb label and the product, so the first paint has it and no URL flashes. --}}
    <title inertia>{{ $page['props']['layout']['documentTitle'] ?? config('app.name') }}</title>
    {{-- The icons are named here, where the brand preflight reads the root layout; the component adds the theme colour, the social image and the brand's colours. --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <x-kit::brand :icons="false" />
    <x-kit::fonts />
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body>@inertia</body>
</html>
