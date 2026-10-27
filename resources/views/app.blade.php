<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            // SEO en el HTML del servidor (no hay SSR de Inertia). Solo el acceso es indexable.
            $seoIndexable = \App\Support\Seo::indexable();
            $seoName = \App\Support\Seo::NAME;
            $seoTitle = request()->routeIs('login')
                ? 'Iniciar sesión · '.$seoName
                : $seoName.' · Control de gastos y requisiciones';
            $seoDescription = \App\Support\Seo::DESCRIPTION;
            $seoImage = url('og-image.png');
            $seoUrl = $seoIndexable ? route('login') : url()->current();
        @endphp
        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="application-name" content="{{ $seoName }}">
        {{-- Las pantallas internas requieren sesión: no se indexan. Solo el acceso sí. --}}
        <meta name="robots" content="{{ $seoIndexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Vista previa al compartir el enlace (WhatsApp, Facebook, LinkedIn, X, Teams) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $seoName }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $seoName }} · Control de gastos">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        @if ($seoIndexable)
            <script type="application/ld+json">{!! json_encode(\App\Support\Seo::jsonLd(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="icon" href="{{ url('favicon.ico') }}">

        {{-- Aplicación instalable (PWA): escritorio con Chrome/Edge y Android --}}
        <link rel="manifest" href="{{ url('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ url('icons/apple-touch-icon.png') }}">
        <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#09090B" media="(prefers-color-scheme: dark)">
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
