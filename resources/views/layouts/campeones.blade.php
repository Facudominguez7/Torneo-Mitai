<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home campeones-pagina">
    <div class="relative flex min-h-screen flex-col">
        @include('layouts.navigation')

        <main class="campeones-contenido flex-1">
            <header class="campeones-intro">
                <div>
                    <span class="campeones-rotulo">Historial del torneo</span>
                    <h1>Campeones de cada categoría</h1>
                    <p>Reviví los equipos que levantaron la copa en la edición seleccionada.</p>
                </div>
                <div class="campeones-selector">
                    <span class="campeones-selector-rotulo">Edición</span>
                    <details class="campeones-selector-menu" onkeydown="if (event.key === 'Escape' && this.open) { this.open = false; this.querySelector('summary').focus(); }">
                        <summary>{{ $EdicionSeleccionada->nombre ?? 'Seleccioná una edición' }}</summary>
                        <div class="campeones-selector-lista">
                            @foreach ($ediciones as $edicion)
                                <a href="{{ route('campeones', ['idEdicion' => $edicion->id]) }}" @if (optional($EdicionSeleccionada)->id == $edicion->id) aria-current="page" @endif>
                                    <span>{{ $edicion->nombre }}</span>
                                    @if (optional($EdicionSeleccionada)->id == $edicion->id)
                                        <span aria-hidden="true">✓</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                    <noscript>
                        <style>.campeones-selector-menu, .campeones-selector-rotulo { display: none; }</style>
                        <form method="GET" action="{{ route('campeones') }}" class="campeones-selector">
                            <label for="idEdicion">Edición</label>
                            <select id="idEdicion" name="idEdicion" onchange="this.form.submit()">
                                <option value="">Seleccioná una edición</option>
                                @foreach ($ediciones as $edicion)
                                    <option value="{{ $edicion->id }}" @selected(optional($EdicionSeleccionada)->id == $edicion->id)>{{ $edicion->nombre }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Ver edición</button>
                        </form>
                    </noscript>
                </div>
            </header>

            @php
                $bloques = [
                    ['titulo' => 'Campeones Copa de Oro', 'copa' => 'Copa de Oro', 'clase' => 'oro', 'coleccion' => $campeonesOro],
                    ['titulo' => 'Campeones Copa de Plata', 'copa' => 'Copa de Plata', 'clase' => 'plata', 'coleccion' => $campeonesPlata],
                    ['titulo' => 'Campeones Copa de Bronce', 'copa' => 'Copa de Bronce', 'clase' => 'bronce', 'coleccion' => $campeonesBronce],
                    ['titulo' => "Campeones Copa Mita'í", 'copa' => "Copa Mita'í", 'clase' => 'mitai', 'coleccion' => $campeonesMitai],
                ];
                $escudoAlternativo = asset('fotos/Logo_Mitai_SinFondo.png');
            @endphp

            @foreach ($bloques as $bloque)
                <section class="campeones-copa campeones-{{ $bloque['clase'] }}" aria-labelledby="copa-{{ $bloque['clase'] }}">
                    <h2 id="copa-{{ $bloque['clase'] }}">{{ $bloque['titulo'] }}</h2>
                    @if ($bloque['coleccion']->isEmpty())
                        <p class="campeones-vacio">No se registraron equipos en esta copa para la edición seleccionada.</p>
                    @else
                        <div class="campeones-lista">
                            @foreach ($bloque['coleccion'] as $equipo)
                                <article class="campeones-tarjeta">
                                    <span class="campeones-categoria">{{ $equipo->categoria->nombreCategoria ?? 'Categoría' }}</span>
                                    <img class="campeones-escudo"
                                        src="{{ !empty($equipo->equipo->foto) ? asset('fotos/equipos/' . $equipo->equipo->foto) : $escudoAlternativo }}"
                                        alt="{{ $equipo->equipo->nombre ?? 'Equipo' }}"
                                        onerror="this.onerror=null;this.src='{{ $escudoAlternativo }}';">
                                    <h3>{{ $equipo->equipo->nombre ?? 'Equipo por confirmar' }}</h3>
                                    <p>{{ $bloque['copa'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </main>
    </div>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
</body>
</html>
