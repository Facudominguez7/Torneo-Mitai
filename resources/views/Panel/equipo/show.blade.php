@extends('Panel.admin')

@section('detalle')
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Detalle de Equipo</h1>
            <a href="{{ route('equipo.index', ['idEdicion' => $EdicionSeleccionada?->id]) }}"
                class="rounded-lg bg-white/90 px-4 py-2 text-sm font-semibold text-gray-800 shadow hover:bg-white">
                Volver
            </a>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-lg">
            <div class="border-b border-gray-100 p-5">
                <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-left">
                    <img
                        class="h-20 w-20 rounded-full border border-gray-200 object-cover"
                        src="{{ asset('fotos/equipos/' . $equipo->foto) }}"
                        alt="Escudo {{ $equipo->nombre }}"
                    >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Equipo</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $equipo->nombre }}</p>
                        <p class="text-sm text-gray-500">{{ $equipo->categoria->nombreCategoria }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 sm:grid-cols-3">
                <div class="border-b border-gray-100 p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">ID</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->id }}</p>
                </div>
                <div class="border-b border-gray-100 p-4 sm:border-b-0 sm:border-r">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Nombre</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->nombre }}</p>
                </div>
                <div class="p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Categoría</p>
                    <p class="mt-2 text-lg font-semibold text-gray-800">{{ $equipo->categoria->nombreCategoria }}</p>
                </div>
            </div>
        </div>

        @php
            $badgeResultado = [
                'G' => ['texto' => 'G', 'clases' => 'bg-green-100 text-green-800'],
                'E' => ['texto' => 'E', 'clases' => 'bg-gray-200 text-gray-700'],
                'P' => ['texto' => 'P', 'clases' => 'bg-red-100 text-red-800'],
            ];
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
            <h2 class="mb-3 text-lg font-bold text-white">Estadísticas de la edición</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-white p-4 shadow-lg" data-estadistica="fase-grupos">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Fase de grupos</h3>
                    @forelse ($estadisticas['faseGrupos'] as $fila)
                        <div class="mt-3">
                            <p class="text-sm font-semibold text-gray-800">{{ $fila['grupo'] ?? 'Sin grupo' }} · {{ $fila['puntos'] }} pts</p>
                            <dl class="mt-2 grid grid-cols-7 gap-1 text-center">
                                @foreach ($columnasTotales as $clave => $etiqueta)
                                    <div>
                                        <dt class="text-xs text-gray-500">{{ $etiqueta }}</dt>
                                        <dd class="text-base font-bold text-gray-800">{{ $fila[$clave] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-500">Sin posiciones cargadas.</p>
                    @endforelse
                </div>

                @foreach (['instanciasFinales' => 'Instancias finales', 'total' => 'Total edición'] as $clave => $titulo)
                    <div class="rounded-xl bg-white p-4 shadow-lg" data-estadistica="{{ $clave }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $titulo }}</h3>
                        <dl class="mt-3 grid grid-cols-7 gap-1 text-center">
                            @foreach ($columnasTotales as $col => $etiqueta)
                                <div>
                                    <dt class="text-xs text-gray-500">{{ $etiqueta }}</dt>
                                    <dd class="text-base font-bold text-gray-800">{{ $estadisticas[$clave][$col] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-xl bg-white shadow-lg" data-detalle-partidos>
            <h2 class="border-b border-gray-100 p-4 text-lg font-bold text-gray-800">Partidos jugados</h2>

            @if (empty($detalle['partidos']))
                <p class="p-5 text-center text-sm text-gray-500">Este equipo todavía no jugó partidos en esta edición</p>
            @else
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Fecha</th>
                                <th class="px-4 py-2">Instancia</th>
                                <th class="px-4 py-2">Condición</th>
                                <th class="px-4 py-2">Rival</th>
                                <th class="px-4 py-2 text-center">Resultado</th>
                                <th class="px-4 py-2 text-center"></th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($detalle['partidos'] as $fila)
                                <tr data-partido-fila>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ $fila['fecha']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                    <td class="px-4 py-2 text-gray-700">
                                        {{ $fila['etapa'] }}
                                        @if ($fila['detalle'])
                                            <span class="block text-xs text-gray-500">{{ $fila['detalle'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">{{ $fila['condicion'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="flex items-center gap-2 text-gray-800">
                                            @if ($fila['rival']?->foto)
                                                <img class="h-7 w-7 rounded-full border border-gray-200 object-cover" src="{{ asset('fotos/equipos/' . $fila['rival']->foto) }}" alt="Escudo {{ $fila['rival']->nombre }}">
                                            @endif
                                            {{ $fila['rival']?->nombre ?? 'Equipo eliminado' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-center font-semibold text-gray-800">
                                        {{ $fila['golesFavor'] }} - {{ $fila['golesContra'] }}
                                        @if ($fila['penalesFavor'] !== null)
                                            <span class="block text-xs font-normal text-gray-500" data-penales>Penales {{ $fila['penalesFavor'] }} - {{ $fila['penalesContra'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <span data-resultado="{{ $fila['resultado'] }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $badgeResultado[$fila['resultado']]['clases'] }}">{{ $fila['resultado'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ $urlPlanilla($fila) }}" class="text-sm font-semibold text-blue-600 hover:underline">Ver planilla</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <ul class="divide-y divide-gray-100 md:hidden">
                    @foreach ($detalle['partidos'] as $fila)
                        <li class="p-4" data-partido-card>
                            <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                                <span>{{ $fila['fecha']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                                <span>{{ $fila['etapa'] }}@if ($fila['detalle']) · {{ $fila['detalle'] }}@endif · {{ $fila['condicion'] }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2 text-gray-800">
                                    @if ($fila['rival']?->foto)
                                        <img class="h-8 w-8 rounded-full border border-gray-200 object-cover" src="{{ asset('fotos/equipos/' . $fila['rival']->foto) }}" alt="Escudo {{ $fila['rival']->nombre }}">
                                    @endif
                                    <span class="truncate">{{ $fila['rival']?->nombre ?? 'Equipo eliminado' }}</span>
                                </span>
                                <span class="flex items-center gap-2 font-semibold text-gray-800">
                                    {{ $fila['golesFavor'] }} - {{ $fila['golesContra'] }}
                                    <span data-resultado="{{ $fila['resultado'] }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $badgeResultado[$fila['resultado']]['clases'] }}">{{ $fila['resultado'] }}</span>
                                </span>
                            </div>
                            @if ($fila['penalesFavor'] !== null)
                                <p class="mt-1 text-xs text-gray-500" data-penales>Penales {{ $fila['penalesFavor'] }} - {{ $fila['penalesContra'] }}</p>
                            @endif
                            <a href="{{ $urlPlanilla($fila) }}" class="mt-2 inline-block text-sm font-semibold text-blue-600 hover:underline">Ver planilla</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-6 rounded-xl bg-white p-4 shadow-lg" data-detalle-goleador>
            <h2 class="text-lg font-bold text-gray-800">Goleador del equipo</h2>

            @if (empty($detalle['goleadores']['maximos']))
                <p class="mt-3 text-sm text-gray-500">Todavía no hay goles cargados</p>
            @else
                <ul class="mt-3 space-y-1">
                    @foreach ($detalle['goleadores']['maximos'] as $goleador)
                        <li class="text-base font-semibold text-gray-800">
                            {{ $goleador['nombre'] }}
                            <span class="text-sm font-normal text-gray-500">· {{ $goleador['goles'] }} {{ $goleador['goles'] === 1 ? 'gol' : 'goles' }}</span>
                        </li>
                    @endforeach
                </ul>

                <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Máximos goleadores</h3>
                <ol class="mt-2 divide-y divide-gray-100 text-sm" data-ranking-goleadores>
                    @foreach ($detalle['goleadores']['ranking'] as $posicion => $goleador)
                        <li class="flex items-center justify-between gap-3 py-2 text-gray-700">
                            <span>{{ $posicion + 1 }}. {{ $goleador['nombre'] }}</span>
                            <span class="font-semibold text-gray-800">{{ $goleador['goles'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
@endsection
