<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;

// Helpers duplicados de FixtureExploradorTest con nombres propios: las funciones
// de Pest son globales y no pueden redeclararse entre archivos del mismo run.
function fixtureVistaBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Fixture Explorador']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $categoriaB = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'categoriaB', 'grupo');
}

function fechaVista(array $contexto, string $nombre, ?int $idCategoria = null): Fecha
{
    return Fecha::create([
        'idCategoria' => $idCategoria,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function partidoVista(
    array $contexto,
    Fecha $fecha,
    string $horario,
    string $local = 'Leones del Norte',
    string $visitante = 'Tigres del Sur',
    int $jugado = 0,
    ?int $idCategoria = null,
): Partido {
    $equipoLocal = Equipo::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $local,
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $visitante,
        'foto' => 'visita.png',
    ]);

    return Partido::create([
        'idCategoria' => $idCategoria ?? $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? 2 : null,
        'golesEquipoVisitante' => $jugado === 1 ? 1 : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

function anclaVistaConTexto(string $html, string $texto): ?string
{
    preg_match_all('/<a\b[^>]*>.*?<\/a>/s', $html, $coincidencias);

    foreach ($coincidencias[0] as $ancla) {
        if (str_contains(strip_tags($ancla), $texto)) {
            return $ancla;
        }
    }

    return null;
}

function posicionAnclaVista(string $html, string $texto): int|false
{
    preg_match_all('/<a\b[^>]*>(.*?)<\/a>/s', $html, $coincidencias);

    foreach ($coincidencias[1] as $indice => $contenido) {
        if (trim(strip_tags($contenido)) === $texto) {
            return $indice;
        }
    }

    return false;
}

it('renderiza solo la fecha relevante cuando no viene idFecha', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $proxima = fechaVista($contexto, 'Fecha 2');
    $lejana = fechaVista($contexto, 'Fecha 3');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));
    partidoVista($contexto, $proxima, now()->addDay()->addHours(2)->format('Y-m-d H:i:s'), 'Pumas del Centro', 'Halcones del Este');
    partidoVista($contexto, $lejana, now()->addDays(15)->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(2)
        ->and($html)->toContain('id="fecha-'.$proxima->id.'"')
        ->and($html)->not->toContain('id="fecha-'.$pasada->id.'"');
});

it('renderiza exactamente la fecha pedida con idFecha explicito', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $proxima = fechaVista($contexto, 'Fecha 2');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => $pasada->id,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(1)
        ->and($html)->toContain('id="fecha-'.$pasada->id.'"')
        ->and($html)->not->toContain('id="fecha-'.$proxima->id.'"');
});

it('busca equipos en todas las fechas y agrupa por fecha', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $proxima = fechaVista($contexto, 'Fecha 2');
    $lejana = fechaVista($contexto, 'Fecha 3');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), 'Leones del Norte', 'Tigres del Sur', jugado: 1);
    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'), 'Leones del Norte', 'Pumas del Centro');
    partidoVista($contexto, $lejana, now()->addDays(15)->format('Y-m-d H:i:s'), 'Halcones del Este', 'Tigres del Sur');

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'equipo' => 'Tigres',
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(2)
        ->and($html)->toContain('id="fecha-'.$pasada->id.'"')
        ->and($html)->toContain('id="fecha-'.$lejana->id.'"')
        ->and($html)->not->toContain('id="fecha-'.$proxima->id.'"');
});

it('combina la busqueda de equipo con el estado finalizados', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $lejana = fechaVista($contexto, 'Fecha 2');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), 'Leones del Norte', 'Tigres del Sur', jugado: 1);
    partidoVista($contexto, $lejana, now()->addDays(15)->format('Y-m-d H:i:s'), 'Tigres del Sur', 'Pumas del Centro');

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'equipo' => 'Tigres',
        'estado' => 'finalizados',
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(1)
        ->and($html)->toContain('id="fecha-'.$pasada->id.'"')
        ->and($html)->not->toContain('id="fecha-'.$lejana->id.'"')
        ->and($html)->toContain('Leones Del Norte')
        ->and($html)->not->toContain('Pumas Del Centro');
});

it('trata los comodines % y _ como texto literal en la busqueda', function () {
    $contexto = fixtureVistaBase();
    $primera = fechaVista($contexto, 'Fecha 1');
    $segunda = fechaVista($contexto, 'Fecha 2');

    partidoVista($contexto, $primera, now()->addDay()->format('Y-m-d H:i:s'), '100% Futbol', 'Pepe_gril');
    partidoVista($contexto, $segunda, now()->addDays(2)->format('Y-m-d H:i:s'), 'Equipo Cien Futbol', 'Pepexgril');

    $porcentaje = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'equipo' => '100% F',
    ]));

    $porcentaje->assertOk();
    $htmlPorcentaje = $porcentaje->getContent();

    expect(substr_count($htmlPorcentaje, 'class="partido-card'))->toBe(1)
        ->and($htmlPorcentaje)->toContain('100% Futbol')
        ->and($htmlPorcentaje)->not->toContain('Equipo Cien Futbol');

    $guionBajo = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'equipo' => 'Pepe_Gril',
    ]));

    $guionBajo->assertOk();
    $htmlGuion = $guionBajo->getContent();

    expect(substr_count($htmlGuion, 'class="partido-card'))->toBe(1)
        ->and($htmlGuion)->toContain('Pepe_gril')
        ->and($htmlGuion)->not->toContain('Pepexgril');
});

it('da precedencia a la busqueda de equipo sobre idFecha=todas', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $proxima = fechaVista($contexto, 'Fecha 2');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), 'Leones del Norte', 'Tigres del Sur', jugado: 1);
    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'), 'Leones del Norte', 'Cóndores del Oeste');
    partidoVista($contexto, $proxima, now()->addDays(2)->format('Y-m-d H:i:s'), 'Pumas del Centro', 'Halcones del Este');

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => 'todas',
        'equipo' => 'Tigres',
    ]));

    $respuesta->assertOk();

    expect(substr_count($respuesta->getContent(), 'class="partido-card'))->toBe(1);
});

it('cae a la fecha relevante con un idFecha numerico inexistente', function () {
    $contexto = fixtureVistaBase();
    $proxima = fechaVista($contexto, 'Fecha 1');

    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => 999999,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect(substr_count($html, 'class="partido-card'))->toBe(1)
        ->and($html)->toContain('id="fecha-'.$proxima->id.'"');
});

it('muestra los chips de fecha en orden descendente con Todas primero', function () {
    $contexto = fixtureVistaBase();
    $primera = fechaVista($contexto, 'Jornada Uno');
    $segunda = fechaVista($contexto, 'Jornada Dos');
    $tercera = fechaVista($contexto, 'Jornada Tres');

    partidoVista($contexto, $primera, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoVista($contexto, $segunda, now()->addDay()->format('Y-m-d H:i:s'));
    partidoVista($contexto, $tercera, now()->addDays(2)->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();
    $posTodas = posicionAnclaVista($html, 'Todas');
    $posTercera = posicionAnclaVista($html, 'Jornada Tres');
    $posSegunda = posicionAnclaVista($html, 'Jornada Dos');
    $posPrimera = posicionAnclaVista($html, 'Jornada Uno');

    expect($posTodas)->not->toBeFalse()
        ->and($posTercera)->not->toBeFalse()
        ->and($posSegunda)->not->toBeFalse()
        ->and($posPrimera)->not->toBeFalse()
        ->and($posTodas)->toBeLessThan($posTercera)
        ->and($posTercera)->toBeLessThan($posSegunda)
        ->and($posSegunda)->toBeLessThan($posPrimera);

    $respuestaTodas = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => 'todas',
    ]));

    $respuestaTodas->assertOk();
    $htmlTodas = $respuestaTodas->getContent();

    expect(posicionAnclaVista($htmlTodas, 'Todas'))->toBeLessThan(posicionAnclaVista($htmlTodas, 'Jornada Tres'))
        ->and(posicionAnclaVista($htmlTodas, 'Jornada Tres'))->toBeLessThan(posicionAnclaVista($htmlTodas, 'Jornada Dos'))
        ->and(posicionAnclaVista($htmlTodas, 'Jornada Dos'))->toBeLessThan(posicionAnclaVista($htmlTodas, 'Jornada Uno'));
});

it('marca el chip de la fecha activa y enlaza las demas con idFecha', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $proxima = fechaVista($contexto, 'Fecha 2');
    $lejana = fechaVista($contexto, 'Fecha 3');

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoVista($contexto, $proxima, now()->addDay()->format('Y-m-d H:i:s'));
    partidoVista($contexto, $lejana, now()->addDays(15)->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => $pasada->id,
        'estado' => 'finalizados',
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();
    $chipPasada = anclaVistaConTexto($html, 'Fecha 1');
    $chipProxima = anclaVistaConTexto($html, 'Fecha 2');
    $chipLejana = anclaVistaConTexto($html, 'Fecha 3');
    $chipTodas = anclaVistaConTexto($html, 'Todas');

    expect($chipPasada)->not->toBeNull()
        ->and($chipProxima)->not->toBeNull()
        ->and($chipLejana)->not->toBeNull()
        ->and($chipTodas)->not->toBeNull()
        ->and($chipPasada)->toContain('idFecha='.$pasada->id)
        ->and($chipPasada)->toContain('aria-current="page"')
        ->and($chipProxima)->toContain('estado=finalizados')
        ->and($chipProxima)->not->toContain('idFecha=')
        ->and($chipProxima)->not->toContain('aria-current')
        ->and($chipLejana)->toContain('idFecha='.$lejana->id)
        ->and($chipLejana)->not->toContain('aria-current')
        ->and($chipTodas)->toContain('idFecha=todas');
});

it('los chips de categoria reinician fecha estado y busqueda', function () {
    $contexto = fixtureVistaBase();
    $pasada = fechaVista($contexto, 'Fecha 1');
    $fechaB = fechaVista($contexto, 'Fecha B', $contexto['categoriaB']->id);
    $grupoB = Grupo::create(['idCategoria' => $contexto['categoriaB']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Grupo B']);

    partidoVista($contexto, $pasada, now()->subDays(7)->format('Y-m-d H:i:s'), jugado: 1);
    partidoVista([...$contexto, 'grupo' => $grupoB], $fechaB, now()->addDay()->format('Y-m-d H:i:s'), idCategoria: $contexto['categoriaB']->id);

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => $pasada->id,
        'estado' => 'finalizados',
        'equipo' => 'Tigres',
    ]));

    $respuesta->assertOk();
    $chipCategoriaB = anclaVistaConTexto($respuesta->getContent(), 'Sub 16');

    expect($chipCategoriaB)->not->toBeNull()
        ->and($chipCategoriaB)->toContain('idCategoria='.$contexto['categoriaB']->id)
        ->and($chipCategoriaB)->toContain('idEdicion='.$contexto['edicion']->id)
        ->and($chipCategoriaB)->not->toContain('idFecha')
        ->and($chipCategoriaB)->not->toContain('estado')
        ->and($chipCategoriaB)->not->toContain('equipo');

    $respuestaB = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoriaB']->id,
    ]));

    $respuestaB->assertOk();

    expect(substr_count($respuestaB->getContent(), 'class="partido-card'))->toBe(1);
});

it('expone el buscador de equipo con hidden inputs de contexto', function () {
    $contexto = fixtureVistaBase();
    $fecha = fechaVista($contexto, 'Fecha 1');

    partidoVista($contexto, $fecha, now()->addDay()->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk();
    $html = $respuesta->getContent();

    expect($html)->toContain('<form method="GET"')
        ->and($html)->toContain('type="search" name="equipo"')
        ->and($html)->toContain('name="idEdicion"')
        ->and($html)->toContain('name="idCategoria"')
        ->and($html)->toContain('name="estado"');
});

it('muestra un mensaje cuando la busqueda de equipo no tiene resultados', function () {
    $contexto = fixtureVistaBase();
    $fecha = fechaVista($contexto, 'Fecha 1');

    partidoVista($contexto, $fecha, now()->addDay()->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'equipo' => 'Equipo Inexistente Total',
    ]));

    $respuesta->assertOk();

    expect($respuesta->getContent())->toContain('No se encontraron partidos');
});

it('normaliza anchors legacy de fecha sin emitir el hack de scroll', function () {
    $contexto = fixtureVistaBase();
    $fecha = fechaVista($contexto, 'Fecha 1');

    partidoVista($contexto, $fecha, now()->addDay()->format('Y-m-d H:i:s'));

    $respuesta = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $respuesta->assertOk()
        ->assertDontSee("block: 'center'")
        ->assertDontSee('dataset.jugado')
        ->assertDontSee('pageshow');

    expect($respuesta->getContent())->toContain('replaceState')
        ->and($respuesta->getContent())->toContain('location.reload');
});
