{{--
    Tarjeta de un partido para Panel.partido.index (T5, panel-admin-redesign).

    Extraída a partial para reutilizarse tanto en el listado plano como en el
    listado agrupado por horario (cuando la página está filtrada por una
    única fecha). Recibe $p (el partido) y usa las variables del listado
    padre ($EdicionSeleccionada, $horarioString).
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

    $camposEliminar = array_filter([
        'partido' => $p->id,
        'idEdicion' => $EdicionSeleccionada->id,
        'idCategoria' => request('idCategoria'),
        'idGrupo' => request('idGrupo'),
        'idFecha' => request('idFecha'),
        'horario' => request('horario'),
        'busqueda' => request('busqueda'),
    ], static fn ($valor) => $valor !== null && $valor !== '');
@endphp

<div class="partido-card rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
    data-local="{{ strtolower($p->equipoLocal->nombre) }}" data-visitante="{{ strtolower($p->equipoVisitante->nombre) }}">
    <div class="flex flex-wrap items-center justify-center gap-2 text-center text-sm font-semibold uppercase tracking-wide text-gray-500">
        {{ $p->fecha->nombre }}
        @if ($p->es_interzonal)
            <x-ui.badge variant="warning">Interzonal</x-ui.badge>
        @endif
    </div>

    @if ($p->categoria || $p->grupo)
        <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
            @if ($p->categoria)
                <x-ui.badge variant="info">{{ $p->categoria->nombreCategoria }}</x-ui.badge>
            @endif
            @if ($p->grupo)
                <x-ui.badge variant="success">{{ $p->grupo->nombre }}</x-ui.badge>
            @endif
        </div>
    @endif

    <div class="mt-4 flex flex-col items-center justify-center gap-4 sm:flex-row sm:justify-around">
        <div class="flex flex-col items-center gap-2">
            <img src="{{ asset('fotos/equipos/' . $p->equipoLocal->foto) }}" width="60" height="60" alt="{{ $p->equipoLocal->nombre }}"
                class="h-14 w-14 rounded-full object-cover md:h-[60px] md:w-[60px]" />
            <div class="text-center text-base font-bold leading-tight text-gray-900 md:text-xl">{{ $p->equipoLocal->nombre }}</div>
        </div>
        <div class="text-sm font-semibold text-gray-400 md:text-lg">vs</div>
        <div class="flex flex-col items-center gap-2">
            <img src="{{ asset('fotos/equipos/' . $p->equipoVisitante->foto) }}" width="60" height="60" alt="{{ $p->equipoVisitante->nombre }}"
                class="h-14 w-14 rounded-full object-cover md:h-[60px] md:w-[60px]" />
            <div class="text-center text-base font-bold leading-tight text-gray-900 md:text-xl">{{ $p->equipoVisitante->nombre }}</div>
        </div>
    </div>

    <div class="mt-2 text-center text-2xl font-extrabold text-gray-900 md:text-3xl">
        {{ $p->golesEquipoLocal }} - {{ $p->golesEquipoVisitante }}
    </div>

    <div class="mt-3 flex flex-col items-center justify-center gap-2 text-sm text-gray-600 md:flex-row md:justify-around md:text-base">
        <span class="font-medium">{{ is_null($p->horario) ? \Carbon\Carbon::parse($p->horario_datetime)->format('d-m-Y H:i') : $p->horario . ' PM' }}</span>
        <span class="font-medium">Cancha {{ $p->cancha }}</span>
    </div>

    @if ($estadoGolesLabel)
        <div class="mt-3 flex justify-center">
            <x-ui.badge :variant="$estadoGolesVariant">{{ $estadoGolesLabel }}</x-ui.badge>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-2 px-3 py-3 sm:grid-cols-2 md:mx-auto md:max-w-2xl md:grid-cols-4 md:px-4">
        <x-ui.button size="sm" variant="primary"
            href="{{ route('cargar-resultado', ['idPartido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')]) }}">
            {{ $p->golesEquipoLocal === null && $p->golesEquipoVisitante === null ? 'Cargar resultado' : 'Editar resultado' }}
        </x-ui.button>
        <x-ui.button size="sm" variant="secondary"
            href="{{ route('planilla.show', ['partidoId' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'tipoPartido' => 'partido', 'horario' => $horarioString ?? 0, 'origen' => 'partido-index', 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha'), 'horario_filtro' => request('horario'), 'busqueda' => request('busqueda')]) }}">
            Planillas
        </x-ui.button>
        <x-ui.button size="sm" variant="success"
            href="{{ route('partido.edit', ['partido' => $p->id, 'idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => request('idCategoria'), 'idGrupo' => request('idGrupo'), 'idFecha' => request('idFecha')]) }}">
            Editar
        </x-ui.button>
        <x-ui.confirm-delete size="sm"
            action="{{ route('partido.destroy', $camposEliminar) }}"
            message="¿Eliminar este partido? Se revertirán posiciones, historial y estadísticas asociadas."
        />
    </div>
</div>
