@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
        <x-ui.page-header title="Detalle de Equipo" class="mb-5">
            <x-slot name="actions">
                <x-ui.button href="{{ route('equipo.index', ['idEdicion' => $EdicionSeleccionada?->id]) }}"
                    variant="secondary">
                    Volver
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        <div class="overflow-hidden rounded-xl border border-pa-linea bg-pa-superficie">
            <div class="border-b border-pa-linea-suave p-5">
                <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
                    <img
                        class="h-20 w-20 rounded-full border border-pa-linea object-cover"
                        src="{{ asset('fotos/equipos/' . $equipo->foto) }}"
                        alt="Escudo {{ $equipo->nombre }}"
                    >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">Equipo</p>
                        <p class="text-2xl font-bold text-pa-tinta">{{ $equipo->nombre }}</p>
                        <p class="text-sm text-pa-tinta-suave">{{ ($detalle['categoria'] ?? null)?->nombreCategoria ?? $equipo->categoria?->nombreCategoria }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 sm:grid-cols-3">
                <div class="border-b border-pa-linea-suave p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">ID</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ $equipo->id }}</p>
                </div>
                <div class="border-b border-pa-linea-suave p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">Nombre</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ $equipo->nombre }}</p>
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-pa-tinta-suave">Categoría</p>
                    <p class="mt-2 text-lg font-semibold text-pa-tinta">{{ ($detalle['categoria'] ?? null)?->nombreCategoria ?? $equipo->categoria?->nombreCategoria }}</p>
                </div>
            </div>
        </div>

        @php
            $variantesResultado = ['G' => 'success', 'E' => 'neutral', 'P' => 'danger'];
            $urlPlanilla = fn (array $fila) => route('planilla.show', [
                'partidoId' => $fila['id'],
                'idEdicion' => $detalle['idEdicion'],
                'tipoPartido' => $fila['tipo'],
                'horario' => 0,
                'origen' => 'equipo-detalle',
            ]);
            $estadisticas = $detalle['estadisticas'];
            $columnasTotales = ['pj' => 'PJ', 'g' => 'G', 'e' => 'E', 'p' => 'P', 'gf' => 'GF', 'gc' => 'GC', 'dg' => 'DG'];
        @endphp

        <section class="mt-6" data-detalle-estadisticas>
            <h2 class="mb-3 text-lg font-bold text-pa-tinta">Estadísticas de la edición</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-pa-linea bg-pa-superficie p-4" data-estadistica="fase-grupos">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-pa-tinta-suave">Fase de grupos</h3>
                    @forelse ($estadisticas['faseGrupos'] as $fila)
                        <div class="mt-3">
                            <p class="text-sm font-semibold text-pa-tinta">{{ $fila['grupo'] ?? 'Sin grupo' }} · {{ $fila['puntos'] }} pts</p>
                            <dl class="mt-2 grid grid-cols-7 gap-1 text-center">
                                @foreach ($columnasTotales as $clave => $etiqueta)
                                    <div>
                                        <dt class="text-xs text-pa-tinta-suave">{{ $etiqueta }}</dt>
                                        <dd class="text-base font-bold text-pa-tinta">{{ $fila[$clave] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-pa-tinta-suave">Sin posiciones cargadas.</p>
                    @endforelse
                </div>

                @foreach (['instanciasFinales' => 'Instancias finales', 'total' => 'Total edición'] as $clave => $titulo)
                    <div class="rounded-xl border border-pa-linea bg-pa-superficie p-4" data-estadistica="{{ $clave }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-pa-tinta-suave">{{ $titulo }}</h3>
                        <dl class="mt-3 grid grid-cols-7 gap-1 text-center">
                            @foreach ($columnasTotales as $col => $etiqueta)
                                <div>
                                    <dt class="text-xs text-pa-tinta-suave">{{ $etiqueta }}</dt>
                                    <dd class="text-base font-bold text-pa-tinta">{{ $estadisticas[$clave][$col] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mt-6" data-detalle-partidos>
            <h2 class="mb-3 text-lg font-bold text-pa-tinta">Partidos jugados</h2>

            @if (empty($detalle['partidos']))
                <x-ui.empty-state message="Este equipo todavía no jugó partidos en esta edición" />
            @else
                <x-ui.data-table class="hidden md:block">
                    <x-slot name="head">
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Instancia</th>
                            <th scope="col">Condición</th>
                            <th scope="col">Rival</th>
                            <th scope="col" class="text-center">Resultado</th>
                            <th scope="col" class="text-center"></th>
                            <th scope="col"></th>
                        </tr>
                    </x-slot>

                    <x-slot name="body">
                        @foreach ($detalle['partidos'] as $fila)
                            <tr data-partido-fila>
                                <td class="whitespace-nowrap">{{ $fila['fecha']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                <td>
                                    {{ $fila['etapa'] }}
                                    @if ($fila['detalle'])
                                        <span class="block text-xs text-pa-tinta-suave">{{ $fila['detalle'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $fila['condicion'] }}</td>
                                <td>
                                    <span class="flex items-center gap-2 text-pa-tinta">
                                        @if ($fila['rival']?->foto)
                                            <img class="h-7 w-7 rounded-full border border-pa-linea object-cover" src="{{ asset('fotos/equipos/' . $fila['rival']->foto) }}" alt="Escudo {{ $fila['rival']->nombre }}">
                                        @endif
                                        {{ $fila['rival']?->nombre ?? 'Equipo eliminado' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-center font-semibold text-pa-tinta">
                                    {{ $fila['golesFavor'] }} - {{ $fila['golesContra'] }}
                                    @if ($fila['penalesFavor'] !== null)
                                        <span class="block text-xs font-normal text-pa-tinta-suave" data-penales>Penales {{ $fila['penalesFavor'] }} - {{ $fila['penalesContra'] }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <x-ui.badge :variant="$variantesResultado[$fila['resultado']]" data-resultado="{{ $fila['resultado'] }}">{{ $fila['resultado'] }}</x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <a href="{{ $urlPlanilla($fila) }}" class="whitespace-nowrap text-sm font-semibold text-pa-acento hover:underline">Ver planilla</a>
                                </td>
                            </tr>
                        @endforeach
                    </x-slot>
                </x-ui.data-table>

                <ul class="divide-y divide-pa-linea-suave overflow-hidden rounded-xl border border-pa-linea bg-pa-superficie md:hidden">
                    @foreach ($detalle['partidos'] as $fila)
                        <li class="p-4" data-partido-card>
                            <div class="flex items-center justify-between gap-2 text-xs text-pa-tinta-suave">
                                <span>{{ $fila['fecha']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                                <span>{{ $fila['etapa'] }}@if ($fila['detalle']) · {{ $fila['detalle'] }}@endif · {{ $fila['condicion'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-pa-tinta">
                                    @if ($fila['rival']?->foto)
                                        <img class="h-8 w-8 rounded-full border border-pa-linea object-cover" src="{{ asset('fotos/equipos/' . $fila['rival']->foto) }}" alt="Escudo {{ $fila['rival']->nombre }}">
                                    @endif
                                    <span class="truncate">{{ $fila['rival']?->nombre ?? 'Equipo eliminado' }}</span>
                                </span>
                                <span class="flex items-center gap-2 font-semibold text-pa-tinta">
                                    {{ $fila['golesFavor'] }} - {{ $fila['golesContra'] }}
                                    <x-ui.badge :variant="$variantesResultado[$fila['resultado']]" data-resultado="{{ $fila['resultado'] }}">{{ $fila['resultado'] }}</x-ui.badge>
                                </span>
                            </div>
                            @if ($fila['penalesFavor'] !== null)
                                <p class="mt-1 text-xs text-pa-tinta-suave" data-penales>Penales {{ $fila['penalesFavor'] }} - {{ $fila['penalesContra'] }}</p>
                            @endif
                            <a href="{{ $urlPlanilla($fila) }}" class="mt-2 inline-block text-sm font-semibold text-pa-acento hover:underline">Ver planilla</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-6 rounded-xl border border-pa-linea bg-pa-superficie p-4" data-detalle-goleador>
            <h2 class="text-lg font-bold text-pa-tinta">Goleador del equipo</h2>

            @if (empty($detalle['goleadores']['maximos']))
                <p class="mt-3 text-sm text-pa-tinta-suave">Todavía no hay goles cargados</p>
            @else
                <ul class="mt-3 space-y-1">
                    @foreach ($detalle['goleadores']['maximos'] as $goleador)
                        <li class="text-base font-semibold text-pa-tinta">
                            {{ $goleador['nombre'] }}
                            <span class="text-sm font-normal text-pa-tinta-suave">· {{ $goleador['goles'] }} {{ $goleador['goles'] === 1 ? 'gol' : 'goles' }}</span>
                        </li>
                    @endforeach
                </ul>

                <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-pa-tinta-suave">Máximos goleadores</h3>
                <ol class="mt-2 divide-y divide-pa-linea-suave text-sm" data-ranking-goleadores>
                    @foreach ($detalle['goleadores']['ranking'] as $posicion => $goleador)
                        <li class="flex items-center justify-between gap-3 py-2 text-pa-tinta-media">
                            <span>{{ $posicion + 1 }}. {{ $goleador['nombre'] }}</span>
                            <span class="font-semibold text-pa-tinta">{{ $goleador['goles'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
@endsection
