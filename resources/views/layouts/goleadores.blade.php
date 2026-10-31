<!DOCTYPE html>
<html lang="es">
@include('head')

<body class="tm-home campeones-pagina premios-pagina">
    <div class="relative flex min-h-screen flex-col">
        @include('layouts.navigation')

        <main class="campeones-contenido flex-1">
            <header class="campeones-intro">
                <div>
                    <span class="campeones-rotulo">Goleadores del torneo</span>
                    <h1>Goleadores premiados</h1>
                    <p>Los goleadores de cada categoría en la edición seleccionada.</p>
                </div>
                <div class="campeones-selector">
                    <span class="campeones-selector-rotulo">Edición</span>
                    <details class="campeones-selector-menu premios-selector" onkeydown="if (event.key === 'Escape' && this.open) { this.open = false; this.querySelector('summary').focus(); }">
                        <summary>{{ $EdicionSeleccionada->nombre ?? 'Seleccioná una edición' }}</summary>
                        <div class="campeones-selector-lista">
                            @foreach ($ediciones as $edicion)
                                <a href="{{ route('goleadores', ['idEdicion' => $edicion->id]) }}" @if (optional($EdicionSeleccionada)->id == $edicion->id) aria-current="page" @endif>
                                    <span>{{ $edicion->nombre }}</span>
                                    @if (optional($EdicionSeleccionada)->id == $edicion->id)
                                        <span aria-hidden="true">✓</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                    <noscript>
                        <style>.premios-pagina .campeones-selector-menu, .premios-pagina .campeones-selector-rotulo { display: none; }</style>
                        <form method="GET" action="{{ route('goleadores') }}" class="campeones-selector">
                            <label for="idEdicion">Edición</label>
                            <select id="idEdicion" name="idEdicion">
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

            <section class="premios-seccion" aria-labelledby="premios-titulo">
                <h2 id="premios-titulo">Premiados de cancha</h2>
                @if (count($goleadores) > 0)
                    @php $escudoAlternativo = asset('fotos/Logo_Mitai_SinFondo.png'); @endphp
                    <div class="premios-tablero">
                        @foreach ($goleadores as $goleador)
                            <article class="premios-tarjeta">
                                <span class="premios-categoria">{{ $goleador['categoria_nombre'] }}</span>
                                <img class="premios-escudo"
                                    src="{{ !empty($goleador['foto_equipo']) ? asset('fotos/equipos/' . $goleador['foto_equipo']) : $escudoAlternativo }}"
                                    alt="{{ $goleador['nombre_equipo'] }}"
                                    onerror="this.onerror=null;this.src='{{ $escudoAlternativo }}';">
                                <h3>{{ $goleador['nombre_jugador'] }}</h3>
                                <p>{{ $goleador['nombre_equipo'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="premios-vacio">Sin registros para la edición seleccionada.</p>
                @endif
            </section>
        </main>
    </div>
    <x-links-legales />
    <x-tab-bar-movil :edicion="$EdicionSeleccionada" />
</body>
</html>
