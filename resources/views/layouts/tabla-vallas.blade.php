<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home vallas-pagina">
    <div class="relative flex min-h-screen flex-col">
        @include('layouts.navigation')
        <main class="vallas-contenido flex-1">
            <header class="vallas-intro">
                <span class="vallas-rotulo">{{ $EdicionSeleccionada?->nombre ?? 'Edición' }} · Ranking defensivo</span>
                <h1>Vallas menos vencidas</h1>
                <p>Los equipos con menos goles recibidos en la edición. Incluye fase de grupos e instancias finales.</p>
            </header>
            @php
                $sort = request('sort', 'goles');
                $dir = request('dir', 'asc');
                $toggle = fn ($clave) => $sort === $clave && $dir === 'asc' ? 'desc' : 'asc';
                $arrow = fn ($clave) => $sort === $clave ? ($dir === 'asc' ? '▲' : '▼') : '';
                $queryBase = array_filter(request()->except(['page']));
                $posOffset = ($equipos->currentPage() - 1) * $equipos->perPage();
                $mostrarPodio = $equipos->currentPage() === 1 && $sort === 'goles' && $dir === 'asc';
                $escudoAlternativo = asset('fotos/Logo_Mitai_SinFondo.png');
            @endphp
            <div class="vallas-filtros">
                @if ($categorias->isNotEmpty())
                    <form method="GET" action="{{ route('tabla-vallas') }}" id="filtro-vallas">
                        <input type="hidden" name="idEdicion" value="{{ $EdicionSeleccionada?->id }}">
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="dir" value="{{ $dir }}">
                        <label class="vallas-rotulo" for="categoria-vallas">Categoría</label>
                        <select id="categoria-vallas" name="idCategoria">
                            <option value="">Todas las categorías</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" @selected((string) request('idCategoria') === (string) $categoria->id)>{{ $categoria->nombreCategoria }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit">Aplicar categoría</button></noscript>
                    </form>
                @endif
                <div class="vallas-busqueda">
                    <label class="vallas-rotulo" for="buscador-equipos">Buscar equipo</label>
                    <input id="buscador-equipos" type="search" placeholder="Buscá tu equipo" aria-describedby="vallas-busqueda-ayuda">
                    <span id="vallas-busqueda-ayuda" class="vallas-ayuda">Busca dentro de esta página.</span>
                </div>
            </div>
            @if ($equipos->isEmpty())
                <p class="vallas-vacio">No existen equipos registrados para la edición seleccionada.</p>
            @else
                @if ($mostrarPodio)
                    <section class="vallas-podio" aria-label="Primeros puestos">
                        @foreach ($equipos->take(3) as $equipo)
                            <article class="vallas-card" data-equipo="{{ $equipo->nombreEquipo }}">
                                <span class="vallas-rotulo">{{ $loop->iteration }}° puesto</span>
                                <div class="vallas-equipo">
                                    <img src="{{ $equipo->fotoEquipo ? asset('fotos/equipos/' . $equipo->fotoEquipo) : $escudoAlternativo }}" alt="" onerror="this.onerror=null;this.src='{{ $escudoAlternativo }}';">
                                    <h2>{{ $equipo->nombreEquipo }}</h2>
                                </div>
                                <p><strong>{{ $equipo->golesContra }}</strong> goles recibidos <span>· {{ $equipo->nombreCategoria }}</span></p>
                            </article>
                        @endforeach
                    </section>
                @endif
                <section aria-labelledby="vallas-clasificacion">
                    <h2 id="vallas-clasificacion">Clasificación completa</h2>
                    <div class="vallas-tabla">
                        <table>
                            <thead><tr>
                                <th scope="col">#</th>
                                @foreach (['equipo' => 'Equipo', 'categoria' => 'Categoría', 'goles' => 'Goles en contra'] as $clave => $titulo)
                                    <th scope="col"><a href="{{ route('tabla-vallas', array_merge($queryBase, ['sort' => $clave, 'dir' => $toggle($clave)])) }}">{{ $titulo }} {{ $arrow($clave) }}</a></th>
                                @endforeach
                            </tr></thead>
                            <tbody>
                                @foreach ($equipos as $index => $equipo)
                                    <tr class="fila-equipo" data-equipo="{{ $equipo->nombreEquipo }}">
                                        <td>{{ $posOffset + $index + 1 }}</td>
                                        <td><div class="vallas-equipo"><img src="{{ $equipo->fotoEquipo ? asset('fotos/equipos/' . $equipo->fotoEquipo) : $escudoAlternativo }}" alt="" onerror="this.onerror=null;this.src='{{ $escudoAlternativo }}';"><strong>{{ $equipo->nombreEquipo }}</strong><span class="vallas-categoria-movil">{{ $equipo->nombreCategoria }}</span></div></td>
                                        <td>{{ $equipo->nombreCategoria }}</td>
                                        <td>{{ $equipo->golesContra }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p id="sin-coincidencias" class="vallas-vacio" hidden role="status">No encontramos equipos que coincidan con tu búsqueda en esta página.</p>
                    <div class="vallas-paginacion">{{ $equipos->links() }}</div>
                </section>
            @endif
        </main>
    </div>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categoria = document.getElementById('categoria-vallas');
            categoria?.addEventListener('change', () => document.getElementById('filtro-vallas').requestSubmit());
            const buscador = document.getElementById('buscador-equipos');
            const normalizar = texto => texto.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
            buscador?.addEventListener('input', () => {
                const termino = normalizar(buscador.value.trim());
                let coincidencias = 0;
                document.querySelectorAll('.fila-equipo, .vallas-card').forEach(elemento => {
                    const coincide = normalizar(elemento.dataset.equipo || '').includes(termino);
                    elemento.hidden = !coincide;
                    if (coincide && elemento.classList.contains('fila-equipo')) coincidencias++;
                });
                const mensaje = document.getElementById('sin-coincidencias');
                if (mensaje) mensaje.hidden = !termino || coincidencias > 0;
            });
        });
    </script>
</body>
</html>
