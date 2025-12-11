<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    {{-- Preconnect para recursos externos (mejora LCP/FCP) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://kit.fontawesome.com" crossorigin>
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">

    {{-- Preload de imagen LCP (la más grande visible al cargar) --}}
    <link rel="preload" as="image" href="{{ asset('fotos/fondo_en_cancha.webp') }}" fetchpriority="high">

    <title>Torneo Mitai</title>
    <link rel="shortcut icon" type="image/png" href="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/welcome.js', 'resources/js/app.js', 'resources/js/admin/admin.js'])

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
</head>