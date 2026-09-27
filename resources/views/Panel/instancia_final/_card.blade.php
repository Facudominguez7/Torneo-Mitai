{{--
    Tarjeta de un partido de instancia final para Panel.instancia_final.index
    (T6, panel-admin-redesign).

    Extraída a partial para el layout de cuadro (bracket): se incluye dentro
    de cada columna de fase. Recibe $p (la instancia final) y usa las
    variables del listado padre ($EdicionSeleccionada, $pendientesPorPartido).
--}}
@php
    $resultadoCargado = $p->golesEquipoLocal !== null && $p->golesEquipoVisitante !== null;
    $estadoGolesLabel = null;
    $estadoGolesVariant = null;

    if ($p->goles_confirmados_at !== null) {
        $estadoGolesLabel = 'Goles confirmados';
        $estadoGolesVariant = 'success';
    } elseif ($resultadoCargado) {
        $estadoGolesLabel = 'Goles pendientes de carga';
        $estadoGolesVariant = 'warning';
    }

    $partidoKey = \App\Models\InstanciaFinal::class.'-'.$p->id;
    $pendientesPartido = $pendientesPorPartido->get($partidoKey);

    $hayPenales = $resultadoCargado
        && $p->golesEquipoLocal === $p->golesEquipoVisitante
        && $p->penalesEquipoLocal !== null
        && $p->penalesEquipoVisitante !== null;

    $localGana = $resultadoCargado && (
        $p->golesEquipoLocal > $p->golesEquipoVisitante
        || ($hayPenales && $p->penalesEquipoLocal > $p->penalesEquipoVisitante)
    );
    $visitanteGana = $resultadoCargado && (
        $p->golesEquipoVisitante > $p->golesEquipoLocal
        || ($hayPenales && $p->penalesEquipoVisitante > $p->penalesEquipoLocal)
    );

    $camposEliminar = array_filter([
        'instancia_final' => $p->id,
        'idEdicion' => $EdicionSeleccionada->id,
        'idCategoria' => request('idCategoria'),
        'idFase' => request('idFase'),
        'idCopa' => request('idCopa'),
        'estado' => request('estado'),
        'busqueda' => request('busqueda'),
        'fecha' => request('fecha'),
        'horario' => request('horario'),
    ], static fn ($valor) => $valor !== null && $valor !== '');
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        @if ($resultadoCargado)
            <x-ui.badge variant="success">Jugado</x-ui.badge>
        @else
            <x-ui.badge variant="warning">Pendiente</x-ui.badge>
        @endif
        @if ($estadoGolesLabel)
            <x-ui.badge :variant="$estadoGolesVariant">{{ $estadoGolesLabel }}</x-ui.badge>
        @endif
    </div>

    <div class="mt-3 flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" width="32" height="32"
                alt="{{ $p->equipoLocal->nombre }}" class="h-8 w-8 shrink-0 rounded-full object-cover" />
            <span class="truncate text-sm text-gray-900 {{ $localGana ? 'font-semibold' : 'font-medium' }}">{{ $p->equipoLocal->nombre }}</span>
        </div>
        <span class="text-lg font-bold text-gray-900">{{ $p->golesEquipoLocal ?? '-' }}</span>
    </div>
    <div class="mt-2 flex items-center justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="32" height="32"
                alt="{{ $p->equipoVisitante->nombre }}" class="h-8 w-8 shrink-0 rounded-full object-cover" />
            <span class="truncate text-sm text-gray-900 {{ $visitanteGana ? 'font-semibold' : 'font-medium' }}">{{ $p->equipoVisitante->nombre }}</span>
        </div>
        <span class="text-lg font-bold text-gray-900">{{ $p->golesEquipoVisitante ?? '-' }}</span>
    </div>

    @if ($hayPenales)
        <p class="mt-1 text-center text-xs text-gray-500">Penales: {{ $p->penalesEquipoLocal }} - {{ $p->penalesEquipoVisitante }}</p>
    @endif
    @if (! is_null($p->resultadoGlobal))
        <p class="mt-1 text-center text-xs font-semibold text-[--color-primary]">Global: {{ $p->resultadoGlobal }}</p>
    @endif

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
        <span>{{ \Carbon\Carbon::parse($p->horario)->format('d-m-Y H:i') }}</span>
        <span>Cancha {{ $p->cancha }}</span>
    </div>

    @if ($pendientesPartido)
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($pendientesPartido as $pendiente)
                <x-ui.badge variant="{{ $pendiente['estado'] === 'sin_planilla' ? 'danger' : 'warning' }}">
                    {{ $pendiente['equipo'] }} ({{ $pendiente['condicion'] }}): {{ $pendiente['estado_label'] }}
                </x-ui.badge>
            @endforeach
        </div>
    @endif

    <div class="mt-3 grid grid-cols-2 gap-2">
        <x-ui.button size="sm" variant="primary"
            href="{{ route('cargar-resultado-instancia', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipo' => 'instancia_final', 'idCategoria' => request('idCategoria'), 'idFase' => request('idFase'), 'idCopa' => request('idCopa'), 'estado' => request('estado'), 'busqueda' => request('busqueda'), 'fecha' => request('fecha'), 'horario' => request('horario')]) }}">
            {{ $resultadoCargado ? 'Editar resultado' : 'Cargar resultado' }}
        </x-ui.button>
        <x-ui.button size="sm" variant="secondary"
            href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0, 'origen' => 'instancia-final-index', 'idCategoria' => request('idCategoria'), 'idFase' => request('idFase'), 'idCopa' => request('idCopa'), 'estado' => request('estado'), 'busqueda' => request('busqueda'), 'fecha' => request('fecha'), 'horario_filtro' => request('horario')]) }}">
            Planillas
        </x-ui.button>
        <x-ui.button size="sm" variant="success"
            href="{{ route('instancia_final.edit', ['instancia_final' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idFase' => request('idFase'), 'idCopa' => request('idCopa'), 'estado' => request('estado'), 'busqueda' => request('busqueda'), 'fecha' => request('fecha'), 'horario' => request('horario')]) }}">
            Editar
        </x-ui.button>
        <x-ui.confirm-delete size="sm"
            action="{{ route('instancia_final.destroy', $camposEliminar) }}"
            message="¿Estás seguro de eliminar este partido?"
        />
    </div>
</div>
