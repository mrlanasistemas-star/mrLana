<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $seoTitle = 'MR-Lana ERP · Control de gastos y requisiciones';
            $seoDescription = 'ERP de gastos de Mr. Lana: requisiciones, autorización de pagos, comprobaciones, proveedores y reportes en un solo lugar, desde la web, escritorio o Android.';
            $seoImage = url('og-image.png');
            $seoUrl = url()->current();
        @endphp
        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        {{-- Las pantallas internas requieren sesión: no se indexan. Acceso y guía sí. --}}
        <meta name="robots" content="{{ auth()->check() ? 'noindex, nofollow' : 'index, follow' }}">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Vista previa al compartir el enlace (WhatsApp, Facebook, LinkedIn, X, Teams) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="MR-Lana ERP">
        <meta property="og:locale" content="es_MX">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="Mr. Lana · ERP de gastos">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="icon" href="{{ url('favicon.ico') }}">

        {{-- Aplicación instalable (PWA): escritorio con Chrome/Edge y Android --}}
        <link rel="manifest" href="{{ url('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ url('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#09090B" media="(prefers-color-scheme: dark)">
        <meta name="application-name" content="MR-Lana">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="MR-Lana">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        {{-- Chrome avisa que se puede instalar antes de que cargue la app: se guarda el aviso para «Instalar app». --}}
        <script>
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.__erpInstallPrompt = e;
                window.dispatchEvent(new Event('erp:installable'));
            });
        </script>

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
