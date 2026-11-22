<?php

use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;

function crearContextoPartidosEdicion(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Partidos']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 5']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

function crearEquipoPartidosEdicion(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return $equipo;
}

function crearPartidoPartidosEdicion(array $contexto, Equipo $local, Equipo $visitante, string $horario, ?int $golesLocal = null, ?int $golesVisitante = null): Partido
{
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $golesLocal,
        'golesEquipoVisitante' => $golesVisitante,
        'cancha' => 3,
        'jugado' => 0,
        'horario_datetime' => $horario,
    ]);
}

function dashboardPartidosEdicion(array $contexto, array $query = []): string
{
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);

    $response = test()->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id] + $query));
    $response->assertOk();

    return $response->getContent();
}

/** Fragmento HTML de la tarjeta de un partido en el dashboard. */
function tarjetaDashboardPartido(string $html, Partido $partido): string
{
    $marca = 'data-dashboard-partido="'.$partido->id.'"';
    $inicio = strpos($html, $marca);
    expect($inicio)->not->toBeFalse();

    // Termina en la próxima tarjeta, el próximo grupo de horario o la sección siguiente.
    $finales = array_filter([
        strpos($html, 'data-dashboard-partido="', $inicio + strlen($marca)),
        strpos($html, 'data-dashboard-final="', $inicio),
        strpos($html, 'data-dashboard-horario="', $inicio),
        strpos($html, 'Requiere atención', $inicio),
    ], fn ($posicion) => $posicion !== false);
    expect($finales)->not->toBeEmpty();

    return substr($html, $inicio, min($finales) - $inicio);
}

it('muestra el listado de partidos siempre abierto, antes de Requiere atencion', function () {
    $contexto = crearContextoPartidosEdicion();
    $html = dashboardPartidosEdicion($contexto);

    $posSeccion = strpos($html, '>Todos los partidos de la edición</h2>');
    $posAtencion = strpos($html, 'Requiere atención');
    $posProxima = strpos($html, 'Próxima fecha');
    $posJugadores = strpos($html, 'Jugadores por categoría');

    expect($posSeccion)->not->toBeFalse()
        ->and($posAtencion)->not->toBeFalse()
        ->and($posProxima)->not->toBeFalse()
        ->and($posSeccion)->toBeLessThan($posProxima)
        ->and($posJugadores)->not->toBeFalse()
        ->and($posSeccion)->toBeLessThan($posAtencion)
        ->and($posSeccion)->toBeLessThan($posJugadores);

    // La sección ya no vive dentro de un <details> colapsable.
    expect(substr_count($html, '<details'))->toBe(0);
});

it('agrupa los partidos por horario en orden cronologico', function () {
    $contexto = crearContextoPartidosEdicion();
    $a = crearEquipoPartidosEdicion($contexto, 'Equipo Alfa');
    $b = crearEquipoPartidosEdicion($contexto, 'Equipo Beta');
    $c = crearEquipoPartidosEdicion($contexto, 'Equipo Gamma');
    $d = crearEquipoPartidosEdicion($contexto, 'Equipo Delta');

    crearPartidoPartidosEdicion($contexto, $a, $b, '2026-04-01 12:30:00');
    crearPartidoPartidosEdicion($contexto, $c, $d, '2026-04-01 09:00:00');
    crearPartidoPartidosEdicion($contexto, $b, $c, '2026-04-01 12:30:00');

    $html = dashboardPartidosEdicion($contexto);

    $pos0900 = strpos($html, 'data-dashboard-horario="09:00"');
    $pos1230 = strpos($html, 'data-dashboard-horario="12:30"');

    expect($pos0900)->not->toBeFalse()
        ->and($pos1230)->not->toBeFalse()
        ->and($pos0900)->toBeLessThan($pos1230)
        ->and(substr_count($html, 'data-dashboard-horario="12:30"'))->toBe(1)
        ->and(substr_count($html, 'data-dashboard-partido="'))->toBe(3);
});

it('conserva el filtro de horario, sus opciones y los parametros reenviados', function () {
    $contexto = crearContextoPartidosEdicion();
    $a = crearEquipoPartidosEdicion($contexto, 'Equipo Alfa');
    $b = crearEquipoPartidosEdicion($contexto, 'Equipo Beta');
    $c = crearEquipoPartidosEdicion($contexto, 'Equipo Gamma');
    $d = crearEquipoPartidosEdicion($contexto, 'Equipo Delta');

    $temprano = crearPartidoPartidosEdicion($contexto, $a, $b, '2026-04-01 09:00:00');
    $tarde = crearPartidoPartidosEdicion($contexto, $c, $d, '2026-04-01 12:30:00');

    $html = dashboardPartidosEdicion($contexto);
    expect($html)->toContain('id="horario"')
        ->toContain('name="horario"')
        ->toContain('<option value="">')
        ->toContain('<option value="09:00"')
        ->toContain('<option value="12:30"')
        ->toContain('action="'.e(route('admin', ['idEdicion' => $contexto['edicion']->id])).'"');

    $filtrado = dashboardPartidosEdicion($contexto, ['horario' => '09:00', 'otro' => 'x']);

    expect($filtrado)->toContain('data-dashboard-partido="'.$temprano->id.'"')
        ->not->toContain('data-dashboard-partido="'.$tarde->id.'"')
        ->toContain('Mostrando partidos de las 09:00 hs')
        ->toContain('name="otro" value="x"')
        ->toContain('name="idEdicion" value="'.$contexto['edicion']->id.'"');

    // El filtro activo ofrece un enlace para volver al listado completo.
    expect($filtrado)->toContain('Limpiar')
        ->and($html)->not->toContain('Mostrando partidos de las');
});

it('mantiene en cada tarjeta equipos, marcador, estado y la accion de planillas', function () {
    $contexto = crearContextoPartidosEdicion();
    $local = crearEquipoPartidosEdicion($contexto, 'Mitai Azul');
    $visitante = crearEquipoPartidosEdicion($contexto, 'Mitai Rojo');
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 10:00:00');

    $tarjeta = tarjetaDashboardPartido(dashboardPartidosEdicion($contexto), $partido);

    $urlPlanilla = route('planilla.show', [
        'partidoId' => $partido->id,
        'idEdicion' => $contexto['edicion']->id,
        'tipoPartido' => 'partido',
        'horario' => 0,
        'origen' => 'admin',
        'horario_filtro' => null,
    ]);

    expect($tarjeta)->toContain('Mitai Azul')
        ->toContain('Mitai Rojo')
        ->toMatch('/data-dashboard-centro[^>]*>\s*vs\s*</')
        ->toContain('Cancha 3')
        // La hora va destacada y separada de la fecha (pedido del usuario).
        ->toContain('01-04-2026')
        ->toMatch('/data-dashboard-hora[^>]*>\s*10:00 hs\s*</')
        ->toContain('Planillas')
        ->toContain('href="'.e($urlPlanilla).'"')
        ->toContain('Sub 12');
});

it('no muestra la tarjeta de un partido con goles confirmados', function () {
    $contexto = crearContextoPartidosEdicion();
    $local = crearEquipoPartidosEdicion($contexto, 'Mitai Azul');
    $visitante = crearEquipoPartidosEdicion($contexto, 'Mitai Rojo');
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 10:00:00', 1, 0);
    $partido->forceFill(['goles_confirmados_at' => now()])->save();

    expect(dashboardPartidosEdicion($contexto))->not->toContain('data-dashboard-partido="'.$partido->id.'"');
});

it('sigue mostrando los avisos de asistencia pendientes dentro de la tarjeta del partido', function () {
    $contexto = crearContextoPartidosEdicion();
    $local = crearEquipoPartidosEdicion($contexto, 'Mitai Verde');
    $visitante = crearEquipoPartidosEdicion($contexto, 'Mitai Blanco');
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 11:00:00');

    $tarjeta = tarjetaDashboardPartido(dashboardPartidosEdicion($contexto), $partido);

    expect($tarjeta)->toContain('Mitai Verde')
        ->toContain('Mitai Blanco')
        ->toContain('Sin planilla');
});

it('muestra un estado vacio cuando la edicion no tiene partidos', function () {
    $contexto = crearContextoPartidosEdicion();

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toContain('>Todos los partidos de la edición</h2>')
        ->toContain('No hay partidos pendientes')
        ->not->toContain('data-dashboard-partido="');
});

it('muestra el vs en el partido pendiente y saca la tarjeta del que tiene resultado', function () {
    $contexto = crearContextoPartidosEdicion();
    $jugado = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Jugado'), crearEquipoPartidosEdicion($contexto, 'Visita Jugado'), '2026-04-01 15:00:00', 10, 0);
    $pendiente = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Pendiente'), crearEquipoPartidosEdicion($contexto, 'Visita Pendiente'), '2026-04-01 16:00:00');

    $html = dashboardPartidosEdicion($contexto);
    $tarjetaPendiente = tarjetaDashboardPartido($html, $pendiente);

    expect($html)->not->toContain('data-dashboard-partido="'.$jugado->id.'"')
        ->and($tarjetaPendiente)
        ->toMatch('/data-dashboard-centro[^>]*>\s*vs\s*</')
        ->not->toMatch('/data-dashboard-centro[^>]*>\s*\d/');
});

it('muestra la hora del partido destacada y separada de la fecha', function () {
    $contexto = crearContextoPartidosEdicion();
    $partido = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Hora'), crearEquipoPartidosEdicion($contexto, 'Visita Hora'), '2026-04-01 15:00:00');

    $html = dashboardPartidosEdicion($contexto);
    $tarjeta = tarjetaDashboardPartido($html, $partido);

    expect($tarjeta)
        ->toMatch('/data-dashboard-hora[^>]*>\s*15:00 hs\s*</')
        ->toContain('01-04-2026')
        ->not->toContain('01-04-2026 15:00')
        ->and($html)->toMatch('/data-dashboard-horario="15:00".*?15:00 hs/s');
});

it('agrupa los horarios legacy en texto y deja al final los partidos sin horario', function () {
    $contexto = crearContextoPartidosEdicion();
    $conDatetime = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Datetime'), crearEquipoPartidosEdicion($contexto, 'Visita Datetime'), '2026-04-01 10:00:00');
    $legacy = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Legacy'), crearEquipoPartidosEdicion($contexto, 'Visita Legacy'), '2026-04-01 18:00:00');
    $legacy->forceFill(['horario_datetime' => null, 'horario' => '9.30'])->save();
    $sinHorario = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Sin Hora'), crearEquipoPartidosEdicion($contexto, 'Visita Sin Hora'), '2026-04-01 11:00:00');
    $sinHorario->forceFill(['horario_datetime' => null, 'horario' => null])->save();

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toMatch('/data-dashboard-horario="09:30".*?Local Legacy.*?data-dashboard-horario="10:00".*?Local Datetime.*?data-dashboard-horario="sin-horario".*?Sin horario asignado.*?Local Sin Hora/s');

    // Sin horario_datetime no se muestra la fecha y hora actuales.
    expect(tarjetaDashboardPartido($html, $sinHorario))->not->toContain(now()->format('d-m-Y'));
});

it('no falla si un parametro reenviado llega como array', function () {
    $contexto = crearContextoPartidosEdicion();
    crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Array'), crearEquipoPartidosEdicion($contexto, 'Visita Array'), '2026-04-01 10:00:00');

    $html = dashboardPartidosEdicion($contexto, ['otro' => ['x', 'y']]);

    expect($html)->toContain('Local Array')
        ->not->toContain('name="otro"');
});

function crearFinalPartidosEdicion(array $contexto, string $local, string $visitante, string $horario, ?int $goles = null): InstanciaFinal
{
    $copa = Copa::create(['idEdicion' => $contexto['edicion']->id, 'nombre' => 'Copa '.$local]);
    $fase = Fase::create(['nombre' => 'Fase '.$local]);

    return InstanciaFinal::create([
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => crearEquipoPartidosEdicion($contexto, $local)->id,
        'idEquipoVisitante' => crearEquipoPartidosEdicion($contexto, $visitante)->id,
        'golesEquipoLocal' => $goles,
        'golesEquipoVisitante' => $goles,
        'horario' => $horario,
        'cancha' => '2',
    ]);
}

it('no lista los partidos con resultado ni ofrece sus horarios', function () {
    $contexto = crearContextoPartidosEdicion();
    $jugado = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Jugado'), crearEquipoPartidosEdicion($contexto, 'Visita Jugado'), '2026-04-01 09:00:00', 0, 7);
    $pendiente = crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Pendiente'), crearEquipoPartidosEdicion($contexto, 'Visita Pendiente'), '2026-04-01 12:30:00');

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toContain('data-dashboard-partido="'.$pendiente->id.'"')
        ->not->toContain('data-dashboard-partido="'.$jugado->id.'"')
        ->toContain('<option value="12:30"')
        ->not->toContain('<option value="09:00"')
        ->toContain('1 partido pendiente de la última fecha');
});

it('no lista las instancias finales con resultado ni ofrece sus horarios', function () {
    $contexto = crearContextoPartidosEdicion();
    $jugada = crearFinalPartidosEdicion($contexto, 'Final Jugada', 'Rival Jugada', '2026-04-08 09:00:00', 2);
    $pendiente = crearFinalPartidosEdicion($contexto, 'Final Pendiente', 'Rival Pendiente', '2026-04-08 12:30:00');

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toContain('data-dashboard-final="'.$pendiente->id.'"')
        ->not->toContain('data-dashboard-final="'.$jugada->id.'"')
        ->toContain('<option value="12:30"')
        ->not->toContain('<option value="09:00"');
});

it('muestra las finales pendientes aunque el ultimo dia ya tenga resultados', function () {
    $contexto = crearContextoPartidosEdicion();
    $pendiente = crearFinalPartidosEdicion($contexto, 'Final Anterior', 'Rival Anterior', '2026-04-08 10:00:00');
    $jugada = crearFinalPartidosEdicion($contexto, 'Final Posterior', 'Rival Posterior', '2026-04-15 10:00:00', 1);

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toContain('data-dashboard-final="'.$pendiente->id.'"')
        ->not->toContain('data-dashboard-final="'.$jugada->id.'"');
});

it('avisa que no quedan pendientes cuando todos los partidos tienen resultado', function () {
    $contexto = crearContextoPartidosEdicion();
    crearPartidoPartidosEdicion($contexto, crearEquipoPartidosEdicion($contexto, 'Local Jugado'), crearEquipoPartidosEdicion($contexto, 'Visita Jugado'), '2026-04-01 09:00:00', 1, 1);

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->not->toContain('data-dashboard-partido="')
        ->toContain('No hay partidos pendientes')
        ->toContain('Goles sin confirmar');
});
