@include('head')

{{-- Pantallas de acceso, diseño "Cancha nocturna": fondo navy con las líneas de la cancha y una sola tarjeta. --}}
<body class="bg-brand-navy font-sans text-white antialiased">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
        {{-- Cancha horizontal en escritorio --}}
        <div class="absolute inset-10 hidden rounded border-2 border-white/[0.08] lg:block"></div>
        <div class="absolute inset-y-10 left-1/2 hidden w-0.5 -translate-x-1/2 bg-white/[0.08] lg:block"></div>
        <div class="absolute left-1/2 top-1/2 hidden h-[440px] w-[440px] -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/[0.08] lg:block"></div>
        <div class="absolute left-10 top-1/2 hidden h-[340px] w-40 -translate-y-1/2 border-2 border-l-0 border-white/[0.08] lg:block"></div>
        <div class="absolute left-10 top-1/2 hidden h-[180px] w-[60px] -translate-y-1/2 border-2 border-l-0 border-white/[0.08] lg:block"></div>
        <div class="absolute right-10 top-1/2 hidden h-[340px] w-40 -translate-y-1/2 border-2 border-r-0 border-white/[0.08] lg:block"></div>
        <div class="absolute right-10 top-1/2 hidden h-[180px] w-[60px] -translate-y-1/2 border-2 border-r-0 border-white/[0.08] lg:block"></div>
        {{-- Cancha vertical en mobile y tablet --}}
        <div class="absolute inset-3 rounded border-2 border-white/[0.08] lg:hidden"></div>
        <div class="absolute inset-x-3 top-1/2 h-0.5 -translate-y-1/2 bg-white/[0.08] lg:hidden"></div>
        <div class="absolute left-1/2 top-1/2 h-60 w-60 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/[0.08] lg:hidden"></div>
        <div class="absolute left-1/2 top-3 h-[70px] w-[200px] -translate-x-1/2 border-2 border-t-0 border-white/[0.08] lg:hidden"></div>
        <div class="absolute bottom-3 left-1/2 h-[70px] w-[200px] -translate-x-1/2 border-2 border-b-0 border-white/[0.08] lg:hidden"></div>
    </div>

    <main class="relative flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 sm:py-12">
        <div class="flex w-full max-w-md flex-col gap-5">
            <a href="{{ route('home') }}"
                class="flex items-center gap-3.5 self-start rounded-lg px-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-gold sm:px-0">
                <img src="{{ asset('fotos/Logo_Mitai_SinFondo.png') }}" alt="" class="h-12 w-12 object-contain sm:h-14 sm:w-14">
                <span class="flex flex-col gap-0.5">
                    <span class="text-xl font-bold leading-tight sm:text-[22px]">Torneo Mita'í</span>
                    <span class="text-sm text-white/80 sm:text-[15px]">Fútbol formativo</span>
                </span>
            </a>

            <section class="flex flex-col gap-5 rounded-2xl border border-white/[0.08] bg-brand-navy-deep p-6 sm:p-9">
                {{ $slot }}
            </section>

            {{ $pie ?? '' }}

            <nav aria-label="Legales" class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm">
                <a href="{{ route('terminos') }}" class="text-white/80 underline-offset-4 hover:text-white hover:underline">Términos y condiciones</a>
                <span aria-hidden="true" class="text-white/50">·</span>
                <a href="{{ route('privacidad') }}" class="text-white/80 underline-offset-4 hover:text-white hover:underline">Política de privacidad</a>
            </nav>
        </div>
    </main>
</body>

</html>
