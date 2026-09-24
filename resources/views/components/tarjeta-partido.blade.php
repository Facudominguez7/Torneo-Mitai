@props(['partido', 'interzonal' => false])

@php
    // Tarjeta de partido compartida por fixture y home. La variante interzonal
    // (gradiente ámbar + badge) solo cambia clases de acento: los data-attributes
    // y el id son idénticos porque los filtros JS de fixture dependen de ellos.
    $esFuturo = optional(\Carbon\Carbon::parse($partido->horario_datetime))->isFuture();

    if ($interzonal) {
        $clasesTarjeta = 'partido-card rounded-3xl border border-amber-400/40 bg-gradient-to-br from-brand-navy via-amber-900/30 to-brand-navy px-6 py-6 text-white shadow-[0_24px_60px_-40px_rgba(245,158,11,0.5)] transition hover:border-amber-400/60 md:shadow-[0_32px_80px_-48px_rgba(245,158,11,0.6)]';
        $clasesEstado = 'bg-amber-500/20 text-amber-300 border border-amber-400/50';
        $clasesPunto = 'bg-amber-400';
        $bordeEscudo = 'border-amber-400/30';
        $clasesIconoReloj = 'text-amber-300';
        $clasesBotonPlanilla = 'border border-amber-400/45 bg-black/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-amber-300 transition hover:bg-amber-400/10 focus:outline-none focus:ring-2 focus:ring-amber-400/40';
    } else {
        $clasesTarjeta = 'partido-card rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy via-brand-teal to-brand-navy px-6 py-6 text-white shadow-[0_24px_60px_-40px_rgba(var(--brand-navy)_/_0.75)] transition hover:border-brand-gold/45 md:shadow-[0_32px_80px_-48px_rgba(var(--brand-navy)_/_0.9)]';
        $clasesEstado = 'bg-brand-gold/15 text-brand-gold border border-brand-gold/40';
        $clasesPunto = 'bg-brand-gold';
        $bordeEscudo = 'border-white/20';
        $clasesIconoReloj = 'text-yellow-300';
        $clasesBotonPlanilla = 'border border-brand-gold/40 bg-black/20 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-brand-gold transition hover:bg-brand-gold/10 focus:outline-none focus:ring-2 focus:ring-brand-gold/40';
    }

    $statusLabel = 'Pendiente';
    if ($partido->jugado) {
        $statusLabel = 'Finalizado';
        $clasesEstado = $interzonal
            ? 'bg-gradient-to-r from-amber-600/30 to-amber-700/30 text-amber-200 border border-amber-400/60'
            : 'bg-gradient-to-r from-brand-teal/90 to-brand-navy/90 text-brand-gold border border-brand-gold/45';
    } elseif ($esFuturo) {
        $statusLabel = 'Próximo';
        $clasesEstado = $interzonal
            ? 'bg-gradient-to-r from-amber-500/15 to-amber-600/15 text-white border border-amber-400/40'
            : 'bg-gradient-to-r from-brand-navy/70 to-brand-teal/70 text-white border border-brand-gold/35';
    }

    $publicToken = data_get($partido, 'public_token');
    $planillaPublicaDisponible = filled($publicToken);
    $planillaPublicaUrl = $planillaPublicaDisponible
        ? route('planillas-publicas.show', [
            'tipoPartido' => 'partido',
            'publicToken' => $publicToken,
        ])
        : null;
@endphp

<article class="{{ $clasesTarjeta }}" data-jugado="{{ (int) $partido->jugado }}" data-horario="{{ \Carbon\Carbon::parse($partido->horario_datetime)->toIso8601String() }}" data-local="{{ strtolower($partido->nombre_local) }}" data-visitante="{{ strtolower($partido->nombre_visitante) }}" id="partido-{{ $partido->id }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        @if ($interzonal)
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-1 text-xs font-semibold text-amber-300 border border-amber-400/40">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Interzonal
                </span>
                <span class="text-sm font-semibold uppercase tracking-[0.3em] text-white/65">
                    {{ $partido->fecha->nombre }}
                </span>
            </div>
        @else
            <div class="text-sm font-semibold uppercase tracking-[0.3em] text-white/65">
                {{ $partido->fecha->nombre }}
            </div>
        @endif
        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $clasesEstado }}">
            <span class="inline-flex h-2 w-2 rounded-full {{ $clasesPunto }}"></span>
            {{ $statusLabel }}
        </span>
    </div>

    <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4 md:hidden">
        <div class="flex flex-col items-center gap-3 text-center">
            <div class="h-16 w-16 overflow-hidden rounded-full border {{ $bordeEscudo }} bg-black/25 p-2.5">
                <img src="{{ asset('fotos/equipos/' . $partido->foto_local) }}" alt="{{ $partido->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
            </div>
            <h3 class="text-sm font-bold text-white/95">{{ $partido->nombre_local }}</h3>
        </div>
        <div class="flex min-w-[80px] flex-col items-center gap-1 text-center">
            <span class="text-[11px] font-semibold uppercase tracking-[0.35em] text-white/60">vs</span>
            <span class="text-2xl font-bold text-white">{{ $partido->golesEquipoLocal }} - {{ $partido->golesEquipoVisitante }}</span>
        </div>
        <div class="flex flex-col items-center gap-3 text-center">
            <div class="h-16 w-16 overflow-hidden rounded-full border {{ $bordeEscudo }} bg-black/25 p-2.5">
                <img src="{{ asset('fotos/equipos/' . $partido->foto_visitante) }}" alt="{{ $partido->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
            </div>
            <h3 class="text-sm font-bold text-white/95">{{ $partido->nombre_visitante }}</h3>
        </div>
    </div>

    <div class="mt-6 hidden md:grid md:grid-cols-[1fr_auto_1fr] md:items-center md:gap-6">
        <div class="flex flex-col items-center gap-3 text-center">
            <div class="h-20 w-20 overflow-hidden rounded-full border {{ $bordeEscudo }} bg-black/25 p-3">
                <img src="{{ asset('fotos/equipos/' . $partido->foto_local) }}" alt="{{ $partido->nombre_local }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Local</p>
            <h3 class="text-lg font-bold text-white">{{ $partido->nombre_local }}</h3>
        </div>

        <div class="flex flex-col items-center justify-center gap-1 text-center">
            <span class="text-xs font-semibold uppercase tracking-[0.35em] text-white/40">vs</span>
            <span class="text-3xl font-bold text-white md:text-4xl">{{ $partido->golesEquipoLocal }} - {{ $partido->golesEquipoVisitante }}</span>
        </div>

        <div class="flex flex-col items-center gap-3 text-center">
            <div class="h-20 w-20 overflow-hidden rounded-full border {{ $bordeEscudo }} bg-black/25 p-3">
                <img src="{{ asset('fotos/equipos/' . $partido->foto_visitante) }}" alt="{{ $partido->nombre_visitante }}" class="h-full w-full object-contain" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
            </div>
            <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/50">Visita</p>
            <h3 class="text-lg font-bold text-white">{{ $partido->nombre_visitante }}</h3>
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-3 text-sm text-white/70 md:flex-row md:items-center md:justify-between md:text-white/60">
        <div class="inline-flex items-center gap-2">
            <i class="fa-regular fa-clock {{ $clasesIconoReloj }}"></i>
            <span>{{ \Carbon\Carbon::parse($partido->horario_datetime)->format('d/m/Y · H:i') }}</span>
        </div>
        <div class="inline-flex items-center gap-2">
            <i class="fa-solid fa-location-dot text-emerald-300"></i>
            <span>Cancha {{ $partido->cancha }}</span>
        </div>
    </div>

    <div class="mt-5 flex justify-end">
        @if ($planillaPublicaDisponible)
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full {{ $clasesBotonPlanilla }}"
                x-on:click='abrirPlanilla(@json($planillaPublicaUrl))'
            >
                <i class="fa-regular fa-rectangle-list"></i>
                Ver planilla
            </button>
        @else
            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.25em] text-white/45">
                <i class="fa-regular fa-rectangle-list"></i>
                Planilla no disponible
            </span>
        @endif
    </div>
</article>
