<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home posiciones-pagina">
    <div class="relative flex min-h-screen flex-col">
        @include('layouts.navigation')

        <main class="posiciones-contenido flex-1">
            <h1>Tabla de posiciones</h1>

            @if ($EdicionSeleccionada && $categorias->isNotEmpty())
                <div class="posiciones-filtros">
                    <div class="posiciones-filtro" role="group" aria-label="Cambiar categoría">
                        <strong>Categoría</strong>
                        @foreach ($categorias as $c)
                            <a href="{{ route('tabla-posiciones', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $c->id]) }}" @if ($c->id == $idCategoria) aria-current="page" @endif>{{ $c->nombreCategoria }}</a>
                        @endforeach
                    </div>
                    @if ($grupos->count() > 1)
                        <div class="posiciones-filtro" role="group" aria-label="Cambiar grupo">
                            <strong>Grupo</strong>
                            @foreach ($grupos as $g)
                                <a href="{{ route('tabla-posiciones', ['idEdicion' => $EdicionSeleccionada->id, 'idCategoria' => $idCategoria, 'idGrupo' => $g->id]) }}" @if ($g->id == $idGrupo) aria-current="page" @endif>{{ $g->nombre }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <section class="posiciones-tablero" aria-label="Tabla de posiciones">
                <div class="posiciones-cabecera">
                    <h2>{{ $nombreCategoria }}@if ($GrupoSeleccionado) · {{ $GrupoSeleccionado->nombre }}@endif</h2>
                    @if ($EdicionSeleccionada)<span>{{ $EdicionSeleccionada->nombre }}</span>@endif
                </div>
                <table>
                    <caption>Posiciones del grupo seleccionado</caption>
                    <thead><tr>
                        <th scope="col" aria-label="Puesto">#</th><th scope="col">Equipo</th><th scope="col">Pts</th><th scope="col">J</th><th scope="col">G</th><th scope="col">E</th><th scope="col">P</th><th scope="col">GF</th><th scope="col">GC</th><th scope="col">DF</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($tablaPosiciones as $filaTabla)
                            <tr>
                                <td>{{ $loop->iteration }}°</td>
                                <td><div class="posiciones-equipo">
                                    <img src="{{ asset('fotos/equipos/' . $filaTabla->fotoEquipo) }}" alt="logo {{ $filaTabla->nombreEquipo }}" onerror="this.onerror=null;this.src='{{ asset('fotos/Logo_Mitai_SinFondo.png') }}';">
                                    <span>{{ $filaTabla->nombreEquipo }}</span>
                                    <span class="posiciones-estadisticas-movil">Ganados: {{ $filaTabla->ganado }}, empatados: {{ $filaTabla->empatado }}, perdidos: {{ $filaTabla->perdido }}, goles a favor: {{ $filaTabla->golesFavor }}, goles en contra: {{ $filaTabla->golesContra }}</span>
                                </div></td>
                                <td>{{ $filaTabla->puntos }}</td><td>{{ $filaTabla->jugado }}</td><td>{{ $filaTabla->ganado }}</td><td>{{ $filaTabla->empatado }}</td><td>{{ $filaTabla->perdido }}</td><td>{{ $filaTabla->golesFavor }}</td><td>{{ $filaTabla->golesContra }}</td><td>{{ $filaTabla->diferenciaGoles }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
</body>
</html>
