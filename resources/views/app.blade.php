<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e11d48">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Comere">
    <meta name="color-scheme" content="light">

    {{-- DNS prefetch para domínios externos usados no frontend --}}
    <link rel="dns-prefetch" href="//unpkg.com">
    <link rel="dns-prefetch" href="//tile.openstreetmap.org">
    <link rel="dns-prefetch" href="//nominatim.openstreetmap.org">

    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    @vite('resources/js/app.jsx')
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
