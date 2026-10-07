<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'MrLana') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="icon" href="{{ url('favicon.ico') }}">

        {{-- Aplicación instalable (PWA): escritorio con Chrome/Edge y Android --}}
        <link rel="manifest" href="{{ url('manifest.webmanifest') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ url('icons/favicon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ url('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#09090B" media="(prefers-color-scheme: dark)">
        <meta name="application-name" content="MR-Lana">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="MR-Lana">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        {{-- Colores de marca configurados (primer pintado sin parpadeo) --}}
        <style>{!! \App\Support\BrandCss::render() !!}</style>

        <!-- Scripts -->
        @routes
            @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead

    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
