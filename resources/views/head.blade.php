<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Preconnect para recursos externos (mejora LCP/FCP) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&display=swap">
    <link rel="preconnect" href="https://kit.fontawesome.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">

    {{-- Preload de imagen LCP solo en home y fixture (la más grande visible al cargar) --}}
    @if (request()->routeIs('home', 'fixture'))
        <link rel="preload" as="image" href="{{ asset('fotos/fondo_en_cancha.webp') }}" fetchpriority="high">
    @endif

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#1a365d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Torneo Mitai">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Torneo Mitai">
    <meta name="description" content="{{ $seo['descripcion'] }}">
    <meta name="robots" content="{{ $seo['indexar'] ? 'index, follow' : 'noindex, nofollow' }}">
    <link rel="canonical" href="{{ $seo['canonica'] }}">

    {{-- Vista previa al compartir en redes y mensajería --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $seo['sitio'] }}">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="{{ $seo['titulo'] }}">
    <meta property="og:description" content="{{ $seo['descripcion'] }}">
    <meta property="og:url" content="{{ $seo['canonica'] }}">
    <meta property="og:image" content="{{ $seo['imagen'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    @if ($seo['datosEstructurados'])
        <script type="application/ld+json">{!! json_encode($seo['datosEstructurados'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endif

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}">

    <title>{{ $seo['titulo'] }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}">

    @php
        $esRutaPanel = request()->is('Panel*');
        $assetsVite = [
            'resources/css/app.css',
            'resources/js/app.js',
        ];

        if ($esRutaPanel) {
            $assetsVite[] = 'resources/js/admin/admin.js';
        } else {
            $assetsVite[] = 'resources/js/welcome.js';
        }
    @endphp

    @vite($assetsVite)

    @if ($esRutaPanel)
        {{-- SweetAlert2 para diálogos y notificaciones --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    @endif

    {{-- FontAwesome con carga diferida para no bloquear renderizado --}}
    <script src="https://kit.fontawesome.com/05291cba44.js" crossorigin="anonymous" defer></script>

    {{-- Google Analytics - Carga diferida para no bloquear FCP/LCP --}}
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                var script = document.createElement('script');
                script.src = 'https://www.googletagmanager.com/gtag/js?id=G-H0PZ81M69T';
                script.async = true;
                document.head.appendChild(script);

                script.onload = function () {
                    window.dataLayer = window.dataLayer || [];
                    function gtag() { dataLayer.push(arguments); }
                    gtag('js', new Date());
                    gtag('config', 'G-H0PZ81M69T');
                };
            }, 1500);
        });
    </script>

    @if (! $esRutaPanel)
        <!-- Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => { });
                });
            }
        </script>
    @endif
</head>
