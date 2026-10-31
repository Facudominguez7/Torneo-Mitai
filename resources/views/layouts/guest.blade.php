<!DOCTYPE html>
<html lang="es">
@include('head')

{{-- Pantallas de acceso, diseño "Medio campo": cancha navy con el logo a la izquierda y el formulario sobre fondo claro. --}}
@php
    $compacto = $attributes->has('compacto');
@endphp

<body class="bg-brand-navy-deep font-sans text-brand-navy-deep antialiased">
    <main class="acceso @if ($compacto) acceso--compacto @endif">
        <section class="acceso-cancha">
            <svg aria-hidden="true" class="acceso-lineas acceso-lineas--ancho" viewBox="0 0 830 900" preserveAspectRatio="xMaxYMid slice" fill="none">
                <rect x="40" y="40" width="1600" height="820" rx="4" />
                <line x1="830" y1="40" x2="830" y2="860" />
                <circle cx="830" cy="450" r="200" />
                <rect x="40" y="250" width="170" height="400" />
                <rect x="40" y="355" width="70" height="190" />
                <path d="M210 375 A110 110 0 0 1 210 525" />
            </svg>
            <svg aria-hidden="true" class="acceso-lineas acceso-lineas--angosto" viewBox="0 0 390 300" preserveAspectRatio="xMidYMax slice" fill="none">
                <rect x="12" y="12" width="366" height="420" rx="4" />
                <rect x="115" y="12" width="160" height="56" />
                <rect x="160" y="12" width="70" height="24" />
            </svg>
            <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="" aria-hidden="true" class="acceso-logo-centro">

            <a href="{{ route('home') }}" class="acceso-marca">
                <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="">
                <span>
                    <strong>Torneo Mita'í</strong>
                    <span>Fútbol formativo</span>
                </span>
            </a>

            <div class="acceso-titular">
                <span class="acceso-sobre">{{ $sobre ?? 'Fecha a fecha' }}</span>
                <p class="acceso-titulo">{{ $titular ?? 'Todo lo de tu equipo, en una sola cuenta.' }}</p>
                <p class="acceso-bajada">{{ $bajada ?? 'Fixture, resultados, posiciones y planillas de cada partido de la edición.' }}</p>
            </div>

            <ul class="acceso-accesos" aria-label="Lo que encontrás en tu cuenta">
                <li>Fixture</li>
                <li>Posiciones</li>
                <li>Planillas</li>
            </ul>
        </section>

        <section class="acceso-formulario">
            <div class="acceso-formulario-in">
                {{ $slot }}

                {{ $pie ?? '' }}

                <nav aria-label="Legales" class="acceso-legales">
                    <a href="{{ route('terminos') }}">Términos y condiciones</a>
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('privacidad') }}">Política de privacidad</a>
                </nav>
            </div>
        </section>
    </main>
</body>

</html>
