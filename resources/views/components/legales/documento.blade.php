@props(['titulo', 'actualizado'])

{{-- Página legal de lectura: título, fecha de actualización y secciones con estilos de texto largo. --}}
<x-app-layout>
    <article class="mx-auto w-full max-w-3xl px-4 py-12 text-white sm:px-6 lg:py-16">
        <header class="flex flex-col gap-2 border-b border-white/15 pb-6">
            <p class="text-sm text-white/80">Última actualización: {{ $actualizado }}</p>
            <h1 class="text-3xl font-bold leading-tight sm:text-4xl">{{ $titulo }}</h1>
        </header>

        <div
            class="mt-8 flex flex-col gap-8 text-base leading-relaxed text-white/90 [&_a]:font-semibold [&_a]:text-brand-teal-light [&_a]:underline-offset-4 hover:[&_a]:underline [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-white [&_li]:mt-1.5 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5">
            {{ $slot }}
        </div>
    </article>
</x-app-layout>
