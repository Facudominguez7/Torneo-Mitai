{{--
    Planillas e impresiones (T9b, panel-admin-redesign): hub sobre las
    impresiones que ya existen. Cada tarjeta tiene solo los filtros que su
    ruta acepta de verdad (no hay filtros globales). Atributos que lee el JS
    de abajo:
      - botón `data-print-url`: URL base (incluye la edición);
      - botón `data-print-titulo` (opcional): título del documento al imprimir;
      - select `data-print-param`: nombre del query param que agrega su valor;
      - select `data-print-placeholder`: texto de la URL base que se reemplaza
        por el valor (para parámetros que van en la ruta);
      - select `data-print-requerido`: el botón queda deshabilitado hasta que
        se elija un valor.
    La impresión en sí usa imprimirEnIframe/imprimirConTitulo del layout
    Panel.admin.
--}}
@extends('Panel.admin')

@section('contenido')
    <x-ui.page-header title="Planillas e impresiones"
        subtitle="Todo lo que se imprime para el día de partido, en un solo lugar." />

    @if (! $EdicionSeleccionada)
        <x-ui.empty-state class="mt-6" message="Seleccioná una edición para imprimir"
            description="Elegí una edición en la barra superior para ver las planillas y tablas que podés imprimir." />
    @else
        @php
            $idEdicion = $EdicionSeleccionada->id;
            $opcionesFechas = $fechas->mapWithKeys(fn ($fecha) => [$fecha->id => $fecha->nombre])->all();
            $opcionesCategorias = $categorias->mapWithKeys(fn ($categoria) => [$categoria->id => $categoria->nombreCategoria])->all();
            $opcionesHorarios = $horariosDisponibles->mapWithKeys(fn ($horario) => [$horario => $horario])->all();

            $secciones = [
                [
                    'titulo' => 'Día de partido',
                    'tarjetas' => [
                        [
                            'clave' => 'fixture',
                            'nombre' => 'Fixture de la fecha',
                            'descripcion' => 'Los partidos de una fecha o de toda la edición.',
                            'url' => route('admin.imprimir-fixture', ['idEdicion' => $idEdicion]),
                            'filtro' => ['param' => 'idFecha', 'etiqueta' => 'Fecha', 'vacio' => 'Todas las fechas', 'opciones' => $opcionesFechas],
                        ],
                        [
                            'clave' => 'arbitros',
                            'nombre' => 'Planilla de árbitros',
                            'descripcion' => 'Planilla para los árbitros con los partidos de la jornada.',
                            'nota' => 'Usa la próxima fecha sin partidos jugados.',
                            'url' => route('admin.imprimir-fixture-arbitros-partidos', ['idEdicion' => $idEdicion]),
                            'filtro' => ['param' => 'horario', 'etiqueta' => 'Horario', 'vacio' => 'Todos los horarios', 'opciones' => $opcionesHorarios],
                        ],
                        [
                            'clave' => 'cobro',
                            'nombre' => 'Planilla de cobro',
                            'descripcion' => 'Horario, categoría y equipo, con columnas para Mercado Pago y efectivo y el cierre del día.',
                            'nota' => 'Usa el último día con partidos.',
                            'titulo' => 'Planilla de Cobro',
                            'url' => route('admin.planilla-cobro', ['idEdicion' => $idEdicion]),
                        ],
                    ],
                ],
                [
                    'titulo' => 'Equipos y jugadores',
                    'tarjetas' => [
                        [
                            'clave' => 'listas-buena-fe',
                            'nombre' => 'Listas de buena fe',
                            'descripcion' => 'Las listas de jugadores de los equipos, de todas las categorías o de una.',
                            'url' => route('lista-buena-fe.imprimir-planillas', ['idEdicion' => $idEdicion]),
                            'filtro' => ['param' => 'idCategoria', 'etiqueta' => 'Categoría', 'vacio' => 'Todas las categorías', 'opciones' => $opcionesCategorias],
                        ],
                        [
                            'clave' => 'planillas-partido',
                            'nombre' => 'Planillas de partido',
                            'descripcion' => 'Una planilla por partido de la fecha elegida.',
                            'url' => route('lista-buena-fe.imprimir-planillas-fecha', ['idEdicion' => $idEdicion, 'idFecha' => '__FECHA__']),
                            'filtro' => ['placeholder' => '__FECHA__', 'etiqueta' => 'Fecha', 'vacio' => 'Elegí una fecha', 'opciones' => $opcionesFechas, 'requerido' => true],
                        ],
                    ],
                ],
                [
                    'titulo' => 'Tablas',
                    'tarjetas' => [
                        [
                            'clave' => 'tabla-posiciones',
                            'nombre' => 'Tablas de posiciones',
                            'descripcion' => 'Las posiciones de todas las categorías de la edición.',
                            'url' => route('admin.imprimir-tabla-posiciones', ['idEdicion' => $idEdicion]),
                        ],
                        [
                            'clave' => 'instancias-finales',
                            'nombre' => 'Fixture de instancias finales',
                            'descripcion' => 'Los cruces de las instancias finales.',
                            'url' => route('admin.imprimir-fixture-instancias-finales', ['idEdicion' => $idEdicion]),
                        ],
                        [
                            'clave' => 'arbitros-finales',
                            'nombre' => 'Planilla de árbitros · finales',
                            'descripcion' => 'Planilla para los árbitros de las instancias finales.',
                            'url' => route('admin.imprimir-fixture-arbitros', ['idEdicion' => $idEdicion]),
                            'filtro' => ['param' => 'horario', 'etiqueta' => 'Horario', 'vacio' => 'Todos los horarios', 'opciones' => $opcionesHorarios],
                        ],
                    ],
                ],
            ];
        @endphp

        <div class="mt-6 space-y-8">
            @foreach ($secciones as $seccion)
                <section aria-labelledby="seccion-impresion-{{ $loop->index }}">
                    <h2 id="seccion-impresion-{{ $loop->index }}" class="text-sm font-bold uppercase tracking-wide text-gray-600">
                        {{ $seccion['titulo'] }}
                    </h2>

                    <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($seccion['tarjetas'] as $tarjeta)
                            @php
                                $filtro = $tarjeta['filtro'] ?? null;
                                $idSelect = 'impresion-'.$tarjeta['clave'].'-filtro';
                                $requerido = (bool) ($filtro['requerido'] ?? false);
                                $sinOpciones = $filtro !== null && $requerido && empty($filtro['opciones']);
                            @endphp

                            <article data-impresion="{{ $tarjeta['clave'] }}"
                                class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4">
                                <div>
                                    <h3 class="text-base font-bold text-gray-800">{{ $tarjeta['nombre'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $tarjeta['descripcion'] }}</p>
                                    @isset($tarjeta['nota'])
                                        <p class="mt-1 text-xs text-gray-500">{{ $tarjeta['nota'] }}</p>
                                    @endisset
                                </div>

                                @if ($filtro)
                                    <div>
                                        <label for="{{ $idSelect }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $filtro['etiqueta'] }}</label>
                                        <x-ui.select id="{{ $idSelect }}"
                                            :data-print-param="$filtro['param'] ?? null"
                                            :data-print-placeholder="$filtro['placeholder'] ?? null"
                                            :data-print-requerido="$requerido ? '' : null"
                                            :disabled="$sinOpciones"
                                            onchange="actualizarImpresion(this)">
                                            <option value="">{{ $filtro['vacio'] }}</option>
                                            @foreach ($filtro['opciones'] as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </x-ui.select>
                                        @if ($sinOpciones)
                                            <p class="mt-1 text-xs text-amber-600">Esta edición todavía no tiene fechas cargadas.</p>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-auto pt-1">
                                    <x-ui.button size="sm"
                                        data-print-url="{{ $tarjeta['url'] }}"
                                        :data-print-titulo="$tarjeta['titulo'] ?? null"
                                        :disabled="$requerido"
                                        onclick="imprimirDesdeTarjeta(this)">
                                        Imprimir
                                    </x-ui.button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        function actualizarImpresion(select) {
            if (select.dataset.printRequerido === undefined) {
                return;
            }

            const boton = select.closest('[data-impresion]').querySelector('[data-print-url]');
            boton.disabled = ! select.value;
        }

        function construirUrlImpresion(tarjeta, boton) {
            let url = boton.dataset.printUrl;
            const parametros = new URLSearchParams();

            tarjeta.querySelectorAll('select').forEach(function (select) {
                if (! select.value) {
                    return;
                }

                if (select.dataset.printPlaceholder) {
                    url = url.replace(select.dataset.printPlaceholder, encodeURIComponent(select.value));
                } else if (select.dataset.printParam) {
                    parametros.set(select.dataset.printParam, select.value);
                }
            });

            const query = parametros.toString();
            if (! query) {
                return url;
            }

            return url + (url.includes('?') ? '&' : '?') + query;
        }

        function imprimirDesdeTarjeta(boton) {
            const tarjeta = boton.closest('[data-impresion]');
            const url = construirUrlImpresion(tarjeta, boton);

            if (boton.dataset.printTitulo) {
                imprimirConTitulo(url, boton.dataset.printTitulo);
                return;
            }

            imprimirEnIframe(url);
        }

        // El navegador puede restaurar una fecha elegida (recarga o "atrás") sin disparar change.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-impresion] select[data-print-requerido]').forEach(actualizarImpresion);
        });
    </script>
@endsection
