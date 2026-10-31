<!DOCTYPE html>
<html lang="es">
@include('head')
<body class="finales-pagina">
    <div x-data="crearPlanillaPublica()" class="min-h-screen flex flex-col tm-home">
        @include('layouts.navigation')
        <main class="flex-1">
            <header class="finales-intro">
                <span class="finales-rotulo">Instancias finales</span>
                <h1>Camino a la copa</h1>
                <p>Consultá los cruces de cada fase y abrí la planilla del partido.</p>
            </header>
            <section class="finales-barra" aria-label="Filtros de finales">
                <details class="finales-filtros">
                    <summary>Filtros <span>{{ $EdicionSeleccionada->nombre ?? 'Edición' }} · Categoría, copa y fase</span></summary>
                    <form id="finales-form" method="GET" action="{{ route('instancias-finales') }}">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada?->id }}">
                        <label>Categoría
                            <select name="categoria" id="categoria">
                                <option value="">Todas</option>
                                @foreach ($categorias as $categoria)
                                    <option value="{{ $categoria->id }}" @selected($selectedCategoria == $categoria->id)>{{ $categoria->nombreCategoria }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Copa
                            <select name="copa" id="copa">
                                <option value="">Todas</option>
                                @foreach ($copas as $copa)
                                    <option value="{{ $copa->id }}" @selected($selectedCopa == $copa->id)>{{ $copa->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Fase
                            <select name="fase" id="fase">
                                <option value="">Todas</option>
                                @foreach ($fases as $fase)
                                    <option value="{{ $fase->id }}" @selected($selectedFase == $fase->id)>{{ $fase->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit">Aplicar filtros</button>
                        @if ($selectedCategoria || $selectedCopa || $selectedFase)
                            <a href="{{ route('instancias-finales', ['idEdicion' => $EdicionSeleccionada?->id]) }}">Limpiar filtros</a>
                        @endif
                    </form>
                </details>
            </section>
            <div class="finales-contenido">
                <header class="finales-cabecera"><div><span class="finales-rotulo">Cruces por fase</span><h2>Instancias finales</h2></div><p>{{ $partidos->count() }} partidos</p></header>
                @forelse ($partidos->groupBy('nombre_categoria') as $categoria => $partidosPorCategoria)
                    <section class="finales-grupo">
                        <h2>{{ $categoria }}</h2>
                        @php
                            $etapas = [
                                'anteriores' => ['titulo' => 'Rondas anteriores', 'partidos' => collect()],
                                'semifinal' => ['titulo' => 'Semifinales', 'partidos' => collect()],
                                'final' => ['titulo' => 'Finales', 'partidos' => collect()],
                            ];
                            foreach ($partidosPorCategoria as $partido) {
                                $nombreFase = mb_strtolower(trim((string) $partido->nombre_fase));
                                $etapa = str_starts_with($nombreFase, 'semifinal') ? 'semifinal' : (preg_match('/^(?:gran\s+)?final\b/u', $nombreFase) ? 'final' : 'anteriores');
                                $etapas[$etapa]['partidos']->push($partido);
                            }
                        @endphp
                        <div class="finales-etapas">
                            @foreach ($etapas as $clave => $etapa)
                                @if ($etapa['partidos']->isNotEmpty())
                                    <section class="finales-etapa finales-etapa--{{ $clave }}" data-etapa="{{ $clave }}">
                                        <h3>{{ $etapa['titulo'] }}</h3>
                                        @foreach ($etapa['partidos']->groupBy('nombre_fase')->sortByDesc(fn($items) => $items->first()->orden_fase) as $fase => $partidosPorFase)
                                            <section class="finales-fase">
                                                <h4>{{ $fase }}</h4>
                                                @foreach ($partidosPorFase->groupBy('nombre_copa')->sortBy(fn($items) => $items->first()->orden_copa) as $copa => $partidosPorCopa)
                                                    <div class="finales-copa">
                                                        <h5>{{ $copa ?: 'Sin copa' }}</h5>
                                                        @foreach ($partidosPorCopa as $p)
                                                            @php
                                                                $p->horario_datetime = $p->horario;
                                                                $hayMarcador = !is_null($p->golesEquipoLocal) && !is_null($p->golesEquipoVisitante);
                                                                $penalesPartido = ($hayMarcador && $p->golesEquipoLocal === $p->golesEquipoVisitante && !is_null($p->penalesEquipoLocal) && !is_null($p->penalesEquipoVisitante)) ? $p->penalesEquipoLocal . ' - ' . $p->penalesEquipoVisitante : null;
                                                            @endphp
                                                            <x-tarjeta-partido :partido="$p" tipo-partido="instancia_final" :fecha-label="optional($p->horario ? \Carbon\Carbon::parse($p->horario) : null)->format('d/m/Y') ?? 'Sin fecha'" resultado-label-desktop="Resultado" :penales="$penalesPartido" :resultado-global="$p->resultadoGlobal ?? null" :marcador-cuenta-como-finalizado="true" :finales="true" />
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </section>
                                        @endforeach
                                    </section>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="finales-vacio">
                        @if ($selectedCategoria || $selectedCopa || $selectedFase)
                            <p>No hay partidos de finales para los filtros seleccionados.</p>
                            <a href="{{ route('instancias-finales', ['idEdicion' => $EdicionSeleccionada?->id]) }}">Limpiar filtros</a>
                        @else
                            <p>No hay partidos de finales para esta edición.</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </main>
        <x-planilla-publica-modal />
    </div>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filtros = document.querySelector('.finales-filtros');
            const formulario = document.getElementById('finales-form');
            const barra = document.querySelector('.finales-barra');
            const navegacion = document.getElementById('nav-principal');
            const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
            let animacion = null;

            function ubicarBarra() {
                barra.style.setProperty('--finales-nav-altura', Math.max(0, navegacion.getBoundingClientRect().bottom) + 'px');
            }

            function cerrarYAplicar() {
                filtros.open = false;
                filtros.querySelector('summary').focus({ preventScroll: true });
                formulario.requestSubmit();
            }

            formulario.addEventListener('change', function (evento) {
                if (evento.target.tagName !== 'SELECT') return;
                if (animacion) animacion.cancel();
                if (movimientoReducido.matches || !formulario.animate) {
                    cerrarYAplicar();
                    return;
                }
                animacion = formulario.animate(
                    [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-8px)' }],
                    { duration: 180, easing: 'ease-out' }
                );
                animacion.onfinish = cerrarYAplicar;
            });
            window.addEventListener('scroll', ubicarBarra, { passive: true });
            window.addEventListener('resize', ubicarBarra);
            if ('ResizeObserver' in window) new ResizeObserver(ubicarBarra).observe(navegacion);
            ubicarBarra();
        });
    </script>
</body>
</html>
