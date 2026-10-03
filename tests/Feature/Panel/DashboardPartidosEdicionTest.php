<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
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

    $siguiente = strpos($html, 'data-dashboard-partido="', $inicio + strlen($marca));

    return substr($html, $inicio, $siguiente === false ? null : $siguiente - $inicio);
}

it('muestra el listado de partidos siempre abierto, despues de los KPIs y antes de Requiere atencion', function () {
    $contexto = crearContextoPartidosEdicion();
    $html = dashboardPartidosEdicion($contexto);

    $posKpis = strpos($html, 'Pendientes de carga');
    $posSeccion = strpos($html, '>Todos los partidos de la edición</h2>');
    $posAtencion = strpos($html, 'Requiere atención');
    $posProxima = strpos($html, 'Próxima fecha');
    $posJugadores = strpos($html, 'Jugadores por categoría');

    expect($posKpis)->not->toBeFalse()
        ->and($posSeccion)->not->toBeFalse()
        ->and($posAtencion)->not->toBeFalse()
        ->and($posProxima)->not->toBeFalse()
        ->and($posSeccion)->toBeLessThan($posProxima)
        ->and($posJugadores)->not->toBeFalse()
        ->and($posSeccion)->toBeGreaterThan($posKpis)
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
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 10:00:00', 2, 1);

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
        ->toContain('2 - 1')
        ->toContain('Goles pendientes de carga')
        ->toContain('Cancha 3')
        ->toContain('01-04-2026 10:00')
        ->toContain('Planillas')
        ->toContain('href="'.e($urlPlanilla).'"')
        ->toContain('Sub 12');
});

it('muestra el estado de goles confirmados en la tarjeta', function () {
    $contexto = crearContextoPartidosEdicion();
    $local = crearEquipoPartidosEdicion($contexto, 'Mitai Azul');
    $visitante = crearEquipoPartidosEdicion($contexto, 'Mitai Rojo');
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 10:00:00', 1, 0);
    $partido->forceFill(['goles_confirmados_at' => now()])->save();

    $tarjeta = tarjetaDashboardPartido(dashboardPartidosEdicion($contexto), $partido);

    expect($tarjeta)->toContain('Goles confirmados')->not->toContain('Goles pendientes de carga');
});

it('sigue mostrando los avisos de asistencia pendientes dentro de la tarjeta del partido', function () {
    $contexto = crearContextoPartidosEdicion();
    $local = crearEquipoPartidosEdicion($contexto, 'Mitai Verde');
    $visitante = crearEquipoPartidosEdicion($contexto, 'Mitai Blanco');
    $partido = crearPartidoPartidosEdicion($contexto, $local, $visitante, '2026-04-01 11:00:00', 0, 0);

    $tarjeta = tarjetaDashboardPartido(dashboardPartidosEdicion($contexto), $partido);

    expect($tarjeta)->toContain('Mitai Verde')
        ->toContain('Mitai Blanco')
        ->toContain('Sin planilla');
});

it('muestra un estado vacio cuando la edicion no tiene partidos', function () {
    $contexto = crearContextoPartidosEdicion();

    $html = dashboardPartidosEdicion($contexto);

    expect($html)->toContain('>Todos los partidos de la edición</h2>')
        ->toContain('No hay partidos para mostrar')
        ->not->toContain('data-dashboard-partido="');
});
