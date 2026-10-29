<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;

it('presenta el tablero con fechas, escudos y datos reales del partido', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Tablero']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);
    $local = Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Leones', 'foto' => 'leones.png']);
    $visitante = Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Tigres', 'foto' => 'tigres.png']);
    $partido = Partido::create([
        'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idGrupo' => $grupo->id,
        'idFechas' => $fecha->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id,
        'jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1,
        'horario_datetime' => '2025-01-01 12:00:00', 'cancha' => 1,
    ]);

    $respuesta = $this->get(route('fixture', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idFecha' => $fecha->id]));
    $respuesta->assertOk()->assertDontSee('Agenda oficial')->assertDontSee('Ver instancias finales')
        ->assertSee('<h1 class="sr-only">Fixture</h1>', false)
        ->assertSee('fixture-tablero', false)->assertSee('fixture-timeline', false)
        ->assertSee('fixture-partidos', false)->assertSee('fotos/equipos/leones.png', false)
        ->assertSee('fotos/equipos/tigres.png', false)->assertSee('fotos/Logo_Mitai_SinFondo.png', false)
        ->assertSee('data-jugado="1"', false)->assertSee('id="partido-'.$partido->id.'"', false)
        ->assertSee('Finalizado')->assertSee('Ver planilla');

    $todas = $this->get(route('fixture', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'estado' => 'todos', 'idFecha' => 'todas']));
    $todas->assertOk()->assertSee('id="fixture-fechas-bar" class="relative lg:hidden', false)
        ->assertSee('id="fixture-fechas-spacer" class="hidden lg:hidden"', false)
        ->assertSee('href="#fecha-'.$fecha->id.'"', false)
        ->assertSee('id="fecha-'.$fecha->id.'"', false)
        ->assertSee('fixture-timeline', false);
});

/**
 * Edición con un partido finalizado (2-1) y otro pendiente sin horario, ambos en la misma fecha.
 *
 * @return array{edicion: Edicion, categoria: Categoria, fecha: Fecha, finalizado: Partido, pendiente: Partido}
 */
function tableroEscenario(): array
{
    $edicion = Edicion::create(['nombre' => 'Apertura Tablero']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $grupo = Grupo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Grupo B']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 7']);
    $equipos = collect(['Leones', 'Tigres', 'Pumas', 'Halcones'])->mapWithKeys(fn ($nombre) => [
        $nombre => Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => $nombre, 'foto' => strtolower($nombre).'.png']),
    ]);

    $crear = fn (string $local, string $visitante, array $extra) => Partido::create([
        'idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idGrupo' => $grupo->id,
        'idFechas' => $fecha->id, 'idEquipoLocal' => $equipos[$local]->id, 'idEquipoVisitante' => $equipos[$visitante]->id,
    ] + $extra);

    return [
        'edicion' => $edicion,
        'categoria' => $categoria,
        'fecha' => $fecha,
        'finalizado' => $crear('Leones', 'Tigres', ['jugado' => 1, 'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1, 'horario_datetime' => '2025-01-01 12:00:00', 'cancha' => 1]),
        'pendiente' => $crear('Pumas', 'Halcones', ['jugado' => 0, 'cancha' => 2]),
    ];
}

it('aplica el tema dual de la home al cuerpo del fixture', function () {
    $escenario = tableroEscenario();

    $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id]))
        ->assertOk()
        ->assertSee('class="fixture-tablero bg-tema-fondo text-tema-tinta', false)
        ->assertDontSee('bg-[--color-primary]', false);
});

it('renderiza el fixture con tarjetas compactas que conservan los atributos de los filtros JS', function () {
    $escenario = tableroEscenario();

    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => $escenario['fecha']->id]));

    $respuesta->assertOk()
        ->assertSee('tarjeta-compacta', false)
        ->assertSee('id="partido-'.$escenario['finalizado']->id.'"', false)
        ->assertSee('data-jugado="1"', false)->assertSee('data-jugado="0"', false)
        ->assertSee('data-horario="2025-01-01T12:00:00', false)->assertSee('data-horario=""', false)
        ->assertSee('data-local="leones"', false)->assertSee('data-visitante="halcones"', false)
        ->assertSee('2 - 1')->assertSee('VS')
        ->assertSee('Horario a confirmar · Cancha 2')
        ->assertSee('fotos/equipos/pumas.png', false)
        ->assertSee("this.src='".asset('fotos/Logo_Mitai_SinFondo.png')."'", false)
        ->assertSee('Ver planilla')
        ->assertSee('Sub 12')
        ->assertDontSee('tracking-[0.3em] text-white/50', false);
});

it('mantiene la tarjeta de partido sin cambios cuando no se pide el modo compacto', function () {
    $partido = (object) [
        'id' => 7, 'jugado' => 1, 'horario_datetime' => '2025-01-01 12:00:00', 'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1, 'cancha' => 3, 'nombre_local' => 'Leones', 'nombre_visitante' => 'Tigres',
        'foto_local' => 'a.png', 'foto_visitante' => 'b.png', 'idEdicion' => 1, 'public_token' => 'tok',
        'fecha' => (object) ['nombre' => 'Fecha 1'],
    ];

    $html = (string) $this->blade('<x-tarjeta-partido :partido="$partido" />', ['partido' => $partido]);

    expect($html)
        ->toContain('rounded-3xl border border-brand-gold/30')
        ->toContain('h-20 w-20 overflow-hidden rounded-full')
        ->toContain('Local')
        ->not->toContain('tarjeta-compacta');
});

it('arma la línea de tiempo de fechas con lista, fecha activa y goles reales', function () {
    $escenario = tableroEscenario();

    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => $escenario['fecha']->id]));

    $respuesta->assertOk()
        ->assertSee('<nav aria-label="Cambiar fecha" class="fixture-timeline">', false)
        ->assertSee('<ol class="fixture-timeline-lista">', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('fixture-timeline-goles', false)
        ->assertSee('3 goles');

    $filtrado = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => $escenario['fecha']->id, 'estado' => 'finalizados']));
    $filtrado->assertOk()->assertDontSee('fixture-timeline-goles', false);
});

it('presenta la cabecera de la fecha con kicker, título, día real y resumen de partidos', function () {
    $escenario = tableroEscenario();

    $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => $escenario['fecha']->id]))
        ->assertOk()
        ->assertSee('fixture-kicker', false)->assertSee('Apertura Tablero')
        ->assertSee('Miércoles 1 de enero')
        ->assertSee('fixture-resumen-n">2</span>', false)->assertSee('partidos en la fecha')
        ->assertSee('fixture-grupo', false)->assertSee('Grupo B')
        ->assertDontSee('>Jornada<', false);
});

it('ofrece controles compactos móviles y conserva la búsqueda GET del servidor', function () {
    $escenario = tableroEscenario();

    $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'estado' => 'finalizados', 'equipo' => 'Leones']))
        ->assertOk()
        ->assertSee('id="fixture-compacto"', false)
        ->assertSee('aria-controls="fixture-panel-fecha"', false)
        ->assertSee('aria-controls="fixture-panel-categoria"', false)
        ->assertSee('aria-controls="fixture-panel-buscar"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('name="estado" value="finalizados"', false)
        ->assertSee('type="search" name="equipo" value="Leones"', false)
        ->assertSee('Leones');
});

it('ofrece saltos a fechas renderizadas en todas sin cambiar la selección y mantiene el aviso con el formulario', function () {
    $escenario = tableroEscenario();
    $parametros = ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id];
    $respuesta = $this->get(route('fixture', $parametros + ['idFecha' => 'todas']))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($respuesta->getContent());
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@id="fixture-panel-fecha"]//a[@href="#fecha-'.$escenario['fecha']->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//form[contains(@class,"fixture-buscador-form")]//*[@id="fixture-busqueda-estado"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="fixture-panel-fecha"]//a[contains(@href,"idFecha=todas")]')->length)->toBe(1);

    $individual = $this->get(route('fixture', $parametros + ['idFecha' => $escenario['fecha']->id]))->assertOk();
    $domIndividual = new DOMDocument;
    @$domIndividual->loadHTML($individual->getContent());
    $xpathIndividual = new DOMXPath($domIndividual);
    expect($xpathIndividual->query('//*[@id="fixture-panel-fecha"]//a[starts-with(@href,"#fecha-")]')->length)->toBe(0);
});

it('muestra la lupa compacta y deja espacio para el foco del buscador', function () {
    $escenario = tableroEscenario();
    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id]))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($respuesta->getContent());
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@aria-controls="fixture-panel-buscar"]/*[local-name()="svg"]')->length)->toBe(1)
        ->and(file_get_contents(resource_path('css/app.css')))->toContain('#fixture-panel-buscar:not([hidden]) {');
});

it('mantiene el buscador móvil ancho y legible en ambos lugares', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain(".fixture-buscador-form,\n    .fixture-buscador {\n        width: 100%;")
        ->toContain(".fixture-buscador {\n        min-height: 3rem;")
        ->toContain("flex: 1 1 auto;\n        min-width: 0;\n        font-size: 1rem;");
});

it('mantiene filtros compactos disponibles al desplazarse también en tablet y escritorio', function () {
    $escenario = tableroEscenario();
    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => 'todas']))->assertOk();

    expect($respuesta->getContent())
        ->toContain('id="fixture-filtros-sentinel"')
        ->toContain("document.getElementById('fixture-filtros-sentinel')")
        ->toContain('sentinel.getBoundingClientRect().bottom')
        ->toContain('aria-controls="fixture-panel-fecha"')
        ->toContain('aria-controls="fixture-panel-categoria"')
        ->toContain('aria-controls="fixture-panel-buscar"')
        ->toContain('href="#fecha-'.$escenario['fecha']->id.'"');
});

it('ajusta el margen de los saltos nativos a la navegación y barra compacta', function () {
    $escenario = tableroEscenario();
    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => 'todas']))->assertOk();

    expect($respuesta->getContent())
        ->toContain('href="#fecha-'.$escenario['fecha']->id.'"')
        ->toContain('new ResizeObserver(actualizarCompacta)')
        ->toContain("setProperty('--fixture-salto-altura'")
        ->toContain('botonesCompactos.getBoundingClientRect().height')
        ->and(file_get_contents(resource_path('css/app.css')))
        ->toContain('scroll-margin-top: max(7rem, var(--fixture-salto-altura, 0px));');
});

it('mantiene visible la fecha elegida y conserva el ícono al actualizar la búsqueda', function () {
    $escenario = tableroEscenario();
    $respuesta = $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id, 'idFecha' => 'todas']))->assertOk();
    $html = $respuesta->getContent();

    expect($html)
        ->toContain('href="#fecha-'.$escenario['fecha']->id.'"')
        ->toContain('function asegurarFechaVisible(')
        ->toContain('compacta.getBoundingClientRect().bottom')
        ->toContain('window.scrollBy(')
        ->toContain('controles.slice(0, 2).forEach(')
        ->not->toContain('controles.forEach(({ boton }, indice) => { boton.textContent');
});

it('ubica categorías y buscador en una única fila de controles', function () {
    $escenario = tableroEscenario();

    $this->get(route('fixture', ['idEdicion' => $escenario['edicion']->id, 'idCategoria' => $escenario['categoria']->id]))
        ->assertOk()
        ->assertSee('fixture-controles', false)->assertSee('fixture-categorias', false)
        ->assertSee('fixture-buscador', false)
        ->assertSee('aria-label="Buscar equipo por nombre"', false);
});
