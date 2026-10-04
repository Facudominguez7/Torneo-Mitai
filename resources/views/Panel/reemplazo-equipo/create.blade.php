@extends('Panel.admin')

@section('contenido')
    @php
        $volverUrl = route('tabla_posicion.index', array_filter([
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'idGrupo' => $idGrupo,
        ]));
        $fila = $vistaPrevia['filaPosicion'];
        $hayElegibles = $equiposElegibles->isNotEmpty();
        $origenInicial = old('origen', $hayElegibles ? 'existente' : 'nuevo');
        $configOtraEdicion = [
            'url' => route('reemplazo-equipo.buscar-otra-edicion'),
            'idEdicion' => $idEdicion,
            'idCategoria' => $idCategoria,
            'recientes' => $candidatosOtraEdicion->values()->all(),
            'elegido' => old('idEquipoOtraEdicion') ? (int) old('idEquipoOtraEdicion') : null,
        ];
    @endphp

    <div class="mx-auto w-full max-w-[640px] px-2 sm:px-6">
        <h1 class="mb-1 text-2xl font-bold text-white">Reemplazar equipo</h1>
        <p class="mb-5 text-white/80">
            {{ $equipoReemplazado->nombre }} · {{ $vistaPrevia['grupo']?->nombre ?? 'Sin grupo' }} · {{ $categoria->nombreCategoria }}
        </p>

        @if ($motivoImposibilidad)
            <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800" role="alert">
                <p class="font-semibold">No se puede reemplazar este equipo.</p>
                <p>{{ $motivoImposibilidad }}</p>
            </div>
            <a href="{{ $volverUrl }}" class="inline-flex rounded-lg bg-gray-500 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Volver a la tabla</a>
        @else
            <section class="mb-5 rounded-xl bg-white/95 p-4 text-gray-800 shadow" aria-labelledby="heredado-titulo">
                <h2 id="heredado-titulo" class="mb-2 font-semibold">Lo que hereda el reemplazante</h2>
                @if ($fila)
                    <div class="mb-3 grid grid-cols-4 gap-2 text-center text-sm sm:grid-cols-8">
                        @foreach (['Puntos' => $fila->puntos, 'PJ' => $fila->jugado, 'G' => $fila->ganado, 'E' => $fila->empatado, 'P' => $fila->perdido, 'GF' => $fila->golesFavor, 'GC' => $fila->golesContra, 'DG' => $fila->diferenciaGoles] as $etiqueta => $valor)
                            <div class="rounded bg-gray-100 p-2">
                                <div class="text-xs font-bold uppercase text-gray-500">{{ $etiqueta }}</div>
                                <div class="font-semibold">{{ $valor }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mb-3 text-sm">El equipo todavía no tiene fila en la tabla de posiciones.</p>
                @endif
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    <li>Partidos pendientes que pasan al reemplazante: <strong>{{ $vistaPrevia['partidosPendientes'] }}</strong></li>
                    <li>Instancias finales pendientes que pasan al reemplazante: <strong>{{ $vistaPrevia['finalesPendientes'] }}</strong></li>
                    <li>Resultados ya jugados que quedan con {{ $equipoReemplazado->nombre }} y no se podrán editar ni eliminar: <strong>{{ $vistaPrevia['resultadosBloqueados'] }}</strong></li>
                </ul>
            </section>

            <form action="{{ route('reemplazo-equipo.store') }}" method="POST" enctype="multipart/form-data"
                x-data="{
                    origen: @js($origenInicial),
                    otra: {
                        config: @js($configOtraEdicion),
                        q: '',
                        items: @js($candidatosOtraEdicion->values()->all()),
                        elegido: @js($configOtraEdicion['elegido']),
                        buscando: false,
                        error: false,
                        temporizador: null,
                        controlador: null,
                        buscar() {
                            clearTimeout(this.temporizador);
                            const texto = this.q.trim();
                            if (texto.length < 2) {
                                if (this.controlador) { this.controlador.abort(); }
                                this.buscando = false;
                                this.error = false;
                                this.items = this.config.recientes;
                                return;
                            }
                            this.temporizador = setTimeout(() => this.consultar(texto), 300);
                        },
                        async consultar(texto) {
                            if (this.controlador) { this.controlador.abort(); }
                            this.controlador = new AbortController();
                            this.buscando = true;
                            this.error = false;
                            const params = new URLSearchParams({ idEdicion: this.config.idEdicion, idCategoria: this.config.idCategoria, q: texto });
                            try {
                                const respuesta = await fetch(this.config.url + '?' + params, {
                                    headers: { 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                    signal: this.controlador.signal,
                                });
                                if (!respuesta.ok) { throw new Error('busqueda'); }
                                this.items = (await respuesta.json()).items;
                                this.buscando = false;
                            } catch (e) {
                                if (e.name === 'AbortError') { return; }
                                this.error = true;
                                this.buscando = false;
                            }
                        },
                    },
                }">
                @csrf
                <input type="hidden" name="idEdicion" value="{{ $idEdicion }}">
                <input type="hidden" name="idCategoria" value="{{ $idCategoria }}">
                <input type="hidden" name="idGrupo" value="{{ $idGrupo }}">
                <input type="hidden" name="idEquipoReemplazado" value="{{ $equipoReemplazado->id }}">

                @include('Panel.fragment._errores-formulario')

                <fieldset class="mb-5 space-y-4">
                    <legend class="mb-2 text-base font-medium text-white">¿Quién reemplaza a {{ $equipoReemplazado->nombre }}?</legend>

                    <div data-opcion-reemplazo="existente" @if ($hayElegibles) @click="origen = 'existente'" @endif class="rounded-lg border-2 bg-white/10 p-4 transition {{ $hayElegibles ? 'cursor-pointer' : 'cursor-not-allowed opacity-70' }}" :class="origen === 'existente' ? 'border-white bg-white/20' : 'border-transparent hover:bg-white/15'">
                        <label class="flex items-center gap-2 font-medium text-white">
                            <input type="radio" name="origen" value="existente" x-model="origen" @disabled(! $hayElegibles)>
                            Equipo ya inscripto
                        </label>
                        @if ($hayElegibles)
                            <select name="idEquipoExistente" class="panel-form-input mt-3" :disabled="origen !== 'existente'">
                                <option value="">Elegí un equipo</option>
                                @foreach ($equiposElegibles as $equipo)
                                    <option value="{{ $equipo->id }}" @selected((string) old('idEquipoExistente') === (string) $equipo->id)>{{ $equipo->nombre }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="mt-2 text-sm text-white/80">No hay equipos disponibles. Para elegir un equipo ya registrado, primero debe estar inscripto en la edición sin grupo.</p>
                        @endif
                    </div>

                    <div data-opcion-reemplazo="nuevo" @click="origen = 'nuevo'" class="rounded-lg border-2 bg-white/10 p-4 transition cursor-pointer" :class="origen === 'nuevo' ? 'border-white bg-white/20' : 'border-transparent hover:bg-white/15'">
                        <label class="flex items-center gap-2 font-medium text-white">
                            <input type="radio" name="origen" value="nuevo" x-model="origen">
                            Equipo nuevo
                        </label>
                        <div class="mt-3 space-y-3">
                            <div>
                                <label for="nombre" class="mb-1 block text-sm text-white">Nombre</label>
                                <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" class="panel-form-input" :disabled="origen !== 'nuevo'">
                            </div>
                            <div>
                                <label for="foto" class="mb-1 block text-sm text-white">Escudo (opcional)</label>
                                <input type="file" name="foto" id="foto" accept="image/png,image/jpeg,image/webp" class="panel-form-input" :disabled="origen !== 'nuevo'">
                            </div>
                        </div>
                    </div>

                    <div data-opcion-reemplazo="otra_edicion" @click="origen = 'otra_edicion'" class="rounded-lg border-2 bg-white/10 p-4 transition cursor-pointer" :class="origen === 'otra_edicion' ? 'border-white bg-white/20' : 'border-transparent hover:bg-white/15'">
                        <label class="flex items-center gap-2 font-medium text-white">
                            <input type="radio" name="origen" value="otra_edicion" x-model="origen">
                            Equipo de otra edición o sin inscribir
                        </label>
                        <p class="mt-1 text-sm text-white/80">Equipos de la categoría {{ $categoria->nombreCategoria }} de ediciones anteriores y equipos de esta categoría que no están inscriptos en esta edición. Se inscribe en esta edición y toma el lugar del reemplazado.</p>

                        <div class="mt-3" x-show="origen === 'otra_edicion'" x-cloak>
                            <label for="buscar-otra-edicion" class="mb-1 block text-sm text-white">Buscar por nombre</label>
                            <input type="search" id="buscar-otra-edicion" autocomplete="off" class="panel-form-input"
                                placeholder="Escribí al menos 2 letras" x-model="otra.q" @input="otra.buscar()" :disabled="origen !== 'otra_edicion'">
                            <p class="mt-2 text-sm text-white/80" x-show="otra.buscando" x-cloak>Buscando...</p>
                            <p class="mt-2 text-sm text-red-200" x-show="otra.error" x-cloak>No se pudo buscar. Probá de nuevo.</p>
                            <p class="mt-2 text-sm text-white/80" x-show="!otra.buscando && !otra.error && otra.items.length === 0" x-cloak>No hay equipos para mostrar.</p>

                            <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto" role="radiogroup" aria-label="Equipos de otras ediciones">
                                <template x-for="item in otra.items" :key="item.id">
                                    <li>
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-white/10 p-2 text-sm text-white">
                                            <input type="radio" :value="item.id" x-model.number="otra.elegido">
                                            <span>
                                                <span class="block font-medium" x-text="item.nombre"></span>
                                                <span class="block text-xs text-white/80" x-text="item.edicion + ' · ' + item.categoria"></span>
                                            </span>
                                        </label>
                                    </li>
                                </template>
                            </ul>
                            <template x-if="origen === 'otra_edicion' && otra.elegido">
                                <input type="hidden" name="idEquipoOtraEdicion" :value="otra.elegido">
                            </template>
                        </div>

                        <noscript>
                            @if ($candidatosOtraEdicion->isNotEmpty())
                                <ul class="mt-3 space-y-2">
                                    @foreach ($candidatosOtraEdicion as $candidato)
                                        <li>
                                            <label class="flex items-center gap-2 text-sm text-white">
                                                <input type="radio" name="idEquipoOtraEdicion" value="{{ $candidato['id'] }}" @checked((string) old('idEquipoOtraEdicion') === (string) $candidato['id'])>
                                                {{ $candidato['nombre'] }} · {{ $candidato['edicion'] }} · {{ $candidato['categoria'] }}
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mt-2 text-sm text-white/80">No hay equipos de otras ediciones ni sin inscribir para mostrar.</p>
                            @endif
                        </noscript>
                    </div>
                </fieldset>

                <label class="mb-5 flex items-start gap-2 text-sm text-white">
                    <input type="checkbox" name="confirmar" value="1" class="mt-1" @checked(old('confirmar'))>
                    <span>Entiendo que el reemplazante hereda la posición y los partidos pendientes, y que los resultados ya jugados de {{ $equipoReemplazado->nombre }} quedan fijados.</span>
                </label>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="panel-form-submit">Confirmar reemplazo</button>
                    <a href="{{ $volverUrl }}" class="inline-flex items-center rounded-lg bg-gray-500 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Cancelar</a>
                </div>
            </form>
        @endif
    </div>
@endsection
