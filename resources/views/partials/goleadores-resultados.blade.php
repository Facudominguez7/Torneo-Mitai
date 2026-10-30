@php
    $sort = $sortKey;
    $toggle = fn($k) => ($sort === $k && $dir === 'asc') ? 'desc' : 'asc';
    $arrow = fn($k) => $sort === $k ? ($dir === 'asc' ? '▲' : '▼') : '';
    $posOffset = ($goleadores_t->currentPage() - 1) * $goleadores_t->perPage();
    $queryBase = array_filter(['idEdicion' => $idEdicion ?? null, 'idCategoria' => $idCategoria ?? null, 'search_value' => $searchValue ?? null]);
    $urlOrden = fn($k) => route('tabla-goleadores', array_merge($queryBase, ['sort' => $k, 'dir' => $toggle($k), 'page' => 1]));
    $urlLimpiarBusqueda = route('tabla-goleadores', array_filter(['idEdicion' => $idEdicion ?? null, 'idCategoria' => $idCategoria ?? null, 'sort' => $sort, 'dir' => $dir, 'page' => 1]));
    $esRankingCanonica = $sort === 'goles' && $dir === 'desc' && $goleadores_t->currentPage() === 1;
    $itemsGoleadores = collect($goleadores_t->items());
    $podio = $esRankingCanonica ? $itemsGoleadores->take(3) : collect();
    $resto = $esRankingCanonica ? $itemsGoleadores->slice(3)->values() : $itemsGoleadores;
    $cantidadPodio = $podio->count();
@endphp

<div class="goleadores-resultados">
    @if ($goleadores_t->isEmpty())
        <div class="goleadores-vacio" data-estado-vacio="{{ $searchValue !== '' ? 'busqueda' : 'filtros' }}">
            @if ($searchValue !== '')
                <p>No encontramos goleadores para «{{ $searchValue }}».</p>
                <a href="{{ $urlLimpiarBusqueda }}" class="limpiar-busqueda-goleadores">Limpiar búsqueda</a>
            @else
                <p>No existen registros para la combinación seleccionada.</p>
            @endif
        </div>
    @else
        @if ($podio->isNotEmpty())
            <div class="goleadores-seccion"><h2>Los primeros goleadores</h2><span>Ranking por goles</span></div>
            <div class="goleadores-podio" data-zona="podio" data-seccion="ranking-movil">
                @foreach ($podio as $index => $g)
                    <article data-variante="podio" data-posicion="{{ $index + 1 }}" data-destacado="{{ $index === 0 ? 'principal' : 'secundario' }}">
                        <span class="goleadores-posicion">{{ $index + 1 }}° puesto · {{ $g->nombreCategoria }}</span>
                        <h3>{{ $g->nombreJugador }}</h3>
                        <p>{{ $g->nombreEquipo }}</p>
                        <div class="goleadores-tanto"><strong>{{ $g->cantidadGoles }}</strong> goles</div>
                    </article>
                @endforeach
            </div>
        @endif
        @if ($resto->isNotEmpty())
            <div class="goleadores-seccion goleadores-seccion-lista"><h2>{{ $cantidadPodio ? 'El resto del ranking' : 'Goleadores' }}</h2><span>{{ $goleadores_t->total() }} resultados</span></div>
            <div class="goleadores-tabla">
                <table>
                    <thead><tr>
                        <th>#</th>
                        @foreach (['nombre' => 'Nombre', 'equipo' => 'Equipo', 'categoria' => 'Categoría', 'goles' => 'Goles'] as $clave => $etiqueta)
                            <th><a class="orden-goleadores" href="{{ $urlOrden($clave) }}">{{ $etiqueta }} {{ $arrow($clave) }}</a></th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach ($resto as $index => $g)
                            @php $posicionCompacta = $posOffset + $cantidadPodio + $index + 1; @endphp
                            <tr data-variante="compacta" data-posicion="{{ $posicionCompacta }}">
                                <td>{{ $posicionCompacta }}</td>
                                <td><strong>{{ $g->nombreJugador }}</strong><span class="goleadores-meta-movil">{{ $g->nombreEquipo }} · {{ $g->nombreCategoria }}</span></td>
                                <td>{{ $g->nombreEquipo }}</td><td>{{ $g->nombreCategoria }}</td><td>{{ $g->cantidadGoles }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@if ($goleadores_t->hasPages())
    <nav class="goleadores-paginacion" aria-label="Paginación">
        @if ($goleadores_t->onFirstPage())
            <span aria-disabled="true">Anterior</span>
        @else
            <a href="{{ $goleadores_t->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif
        <span>Página {{ $goleadores_t->currentPage() }} de {{ $goleadores_t->lastPage() }}</span>
        @if ($goleadores_t->hasMorePages())
            <a href="{{ $goleadores_t->nextPageUrl() }}" rel="next">Siguiente</a>
        @else
            <span aria-disabled="true">Siguiente</span>
        @endif
    </nav>
@endif
