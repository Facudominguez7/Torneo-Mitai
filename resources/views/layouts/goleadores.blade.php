<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="bg-[--color-primary] text-white">
    <div class="relative isolate min-h-screen">
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ asset('fotos/fondo_en_cancha.webp') }}" alt="Cancha de fútbol"
                class="h-full w-full object-cover object-center">
            <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black/90"></div>
        </div>

        <div class="relative flex min-h-screen flex-col">
            @include('layouts.navigation')

            <main class="flex-1">
                <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                        <div class="space-y-3 max-w-2xl">
                            <span
                                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.35em] text-brand-gold/80">
                                <span class="h-2 w-2 rounded-full bg-brand-gold"></span>
                                Artilleros de cada certamen
                            </span>
                            <h1 class="text-3xl sm:text-4xl font-bold text-white">Goleadores del torneo</h1>
                            <p class="text-sm sm:text-base text-white/70">Segu&iacute; de cerca a los máximos anotadores
                                MITA'Í de cada categoría. Usá los filtros para encontrar tu edición favorita</p>
                        </div>
                        <form method="GET" action="{{ route('goleadores') }}"
                            class="grid gap-3 rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy/70 via-brand-teal/70 to-brand-navy/70 px-4 py-4 text-sm text-white/80 shadow-[0_24px_60px_-40px_rgba(var(--brand-navy)_/_0.9)] sm:px-6 sm:py-5">
                            <label for="idEdicion"
                                class="text-xs font-semibold uppercase tracking-[0.3em] text-brand-gold/80">Edición</label>
                            <div class="relative">
                                <div
                                    class="group rounded-2xl bg-gradient-to-r from-brand-navy via-brand-teal to-brand-navy p-[1px] transition focus-within:ring-2 focus-within:ring-brand-gold/50">
                                    <div class="relative rounded-[1.1rem] bg-brand-navy/80">
                                        <select id="idEdicion" name="idEdicion" onchange="this.form.submit()"
                                            class="w-full appearance-none rounded-[1.1rem] border-0 bg-transparent px-4 py-3 text-sm font-semibold text-white focus:outline-none"
                                            style="color-scheme: dark;">
                                            <option value="" style="background-color: var(--color-primary); color: white;">Seleccioná
                                                una edición
                                            </option>
                                            @foreach ($ediciones as $edicion)
                                                <option value="{{ $edicion->id }}"
                                                    style="background-color: var(--color-primary); color: white;" {{ optional($EdicionSeleccionada)->id == $edicion->id ? 'selected' : '' }}>
                                                    {{ $edicion->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span
                                            class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-gold">
                                            <i class="fa-solid fa-chevron-down text-xs"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    @if (count($goleadores) > 0)
                        <div class="mt-10 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($goleadores as $goleador)
                                @php
                                    $totalGoles = $goleador['cantidad_goles'] ?? $goleador['goles'] ?? $goleador['cantidadGoles'] ?? null;
                                @endphp
                                <article
                                    class="group relative overflow-hidden rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy/95 via-brand-teal/92 to-brand-navy/95 p-6 text-white shadow-[0_28px_70px_-40px_rgba(var(--brand-navy)_/_0.85)] transition-transform duration-300 hover:-translate-y-1">
                                    <div
                                        class="absolute -right-14 -top-16 h-36 w-36 rounded-full bg-brand-gold/20 blur-3xl opacity-40 transition-opacity duration-300 group-hover:opacity-70">
                                    </div>
                                    <div class="relative flex h-full flex-col justify-between">
                                        <div
                                            class="flex items-start justify-between text-xs font-semibold uppercase tracking-[0.35em] text-brand-gold/80">
                                            <span>#{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                            <span class="text-white/80">{{ $goleador['categoria_nombre'] }}</span>
                                        </div>
                                        <div class="mt-6 flex flex-col items-center text-center">
                                            <div
                                                class="h-24 w-24 overflow-hidden rounded-full border border-brand-gold/40 bg-brand-navy/60 p-2 shadow-inner shadow-brand-navy/50">
                                                <img src="{{ asset('fotos/equipos/' . ($goleador['foto_equipo'] ?? 'Logo_Mitai_SinFondo.png')) }}"
                                                    alt="{{ $goleador['nombre_equipo'] }}" class="h-full w-full object-contain"
                                                    onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                            </div>
                                            <h3 class="mt-4 text-xl font-bold text-white">
                                                {{ $goleador['nombre_jugador'] }}
                                            </h3>
                                            <p class="mt-1 text-sm text-white/70">{{ $goleador['nombre_equipo'] }}</p>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p
                            class="mt-10 rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy/70 via-brand-teal/70 to-brand-navy/70 px-6 py-10 text-center text-lg font-semibold text-white/80">
                            Sin registros para la edición seleccionada.
                        </p>
                    @endif
                </section>
            </main>

            @include('layouts.footer')
        </div>
    </div>
</body>

</html>
