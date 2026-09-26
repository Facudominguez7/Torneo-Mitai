<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Services\EquiposDeEdicionService;
use App\Services\EstadisticasService;
use App\Services\Panel\ResumenDashboard;
use Illuminate\Support\Carbon;

function crearServicioResumenDashboard(): ResumenDashboard
{
    return new ResumenDashboard(new EquiposDeEdicionService, new EstadisticasService);
}

function crearContextoServicioResumen(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Servicio']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 14',
    ]);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Servicio',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Servicio',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

it('cuenta equipos inscriptos sin duplicar entre inscripcion directa y pivote', function () {
    $contexto = crearContextoServicioResumen();

    $equipoDirecto = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Equipo Directo',
        'foto' => 'e.png',
    ]);

    $equipoPivote = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Equipo Pivote',
        'foto' => 'e.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipoPivote->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['equiposInscriptos'])->toBe(2);
});

it('cuenta solo jugadores activos de la lista de buena fe como habilitados', function () {
    $contexto = crearContextoServicioResumen();
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Equipo Habilitados',
        'foto' => 'e.png',
    ]);

    foreach ([['30100001', true], ['30100002', true], ['30100003', false]] as [$dni, $activo]) {
        Jugador::create(['dni' => $dni, 'nombre' => 'Jugador', 'apellido' => $dni]);
        ListaBuenaFe::create([
            'dni_jugador' => $dni,
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'fecha_nacimiento' => '2013-01-01',
            'fecha_alta' => now(),
            'activo' => $activo,
            'fecha_baja' => $activo ? null : now(),
        ]);
    }

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['jugadoresHabilitados'])->toBe(2);
});

it('arma la lista de partidos pendientes y excluye a los que ya tienen resultado confirmado y asistencia', function () {
    $contexto = crearContextoServicioResumen();
    $local = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Local', 'foto' => 'e.png']);
    $visitante = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Visitante', 'foto' => 'e.png']);

    $partidoSinResultado = Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-05-01 09:00:00',
    ]);

    $partidoConfirmado = Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $visitante->id,
        'idEquipoVisitante' => $local->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 1,
        'goles_confirmados_at' => now(),
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-05-01 10:00:00',
    ]);

    foreach ([[$visitante, '30500001'], [$local, '30500002']] as [$equipo, $dni]) {
        Jugador::create(['dni' => $dni, 'nombre' => 'Jugador', 'apellido' => $dni]);
        PlanillaJugador::create([
            'partido_id' => $partidoConfirmado->id,
            'partido_type' => 'App\\Models\\Partido',
            'dni_jugador' => $dni,
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'numero_camiseta' => 7,
            'goles' => 0,
            'asistio' => true,
            'tarjeta_roja' => false,
        ]);
    }

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['pendientesDeCarga'])->toBe(1);
    $pendiente = $resumen['partidosPendientes']->firstWhere('id', $partidoSinResultado->id);
    expect($pendiente)->not->toBeNull();
    expect($pendiente['estado']['tipo'])->toBe('sin_resultado');
    expect($resumen['partidosPendientes']->firstWhere('id', $partidoConfirmado->id))->toBeNull();
});

it('calcula la proxima fecha con cantidad de partidos canchas distintas y primer horario', function () {
    $this->travelTo(Carbon::parse('2026-05-01 12:00:00'));
    $contexto = crearContextoServicioResumen();
    $local = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Local', 'foto' => 'e.png']);
    $visitante = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Visitante', 'foto' => 'e.png']);

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => '2026-05-02 09:00:00',
    ]);

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $visitante->id,
        'idEquipoVisitante' => $local->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-05-02 08:00:00',
    ]);

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['proximaFecha'])->not->toBeNull();
    expect($resumen['proximaFecha']['nombre'])->toBe('Fecha Servicio');
    expect($resumen['proximaFecha']['cantidadPartidos'])->toBe(2);
    expect($resumen['proximaFecha']['cantidadCanchas'])->toBe(2);
    expect($resumen['proximaFecha']['primerHorario'])->toBe('08:00');
});

it('devuelve una proxima fecha nula cuando la edicion no tiene fechas', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Sin Fechas']);

    $resumen = crearServicioResumenDashboard()->obtenerResumen($edicion->id);

    expect($resumen['proximaFecha'])->toBeNull();
    expect($resumen['partidosPendientes'])->toHaveCount(0);
    expect($resumen['equiposInscriptos'])->toBe(0);
});

function crearPartidoServicioResumen(array $contexto, Fecha $fecha, string $horario, ?int $golesLocal = null, ?int $golesVisitante = null): Partido
{
    $local = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Local '.$horario, 'foto' => 'e.png']);
    $visitante = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Visitante '.$horario, 'foto' => 'e.png']);

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $golesLocal,
        'golesEquipoVisitante' => $golesVisitante,
        'cancha' => 1,
        'jugado' => $golesLocal === null ? 0 : 1,
        'horario_datetime' => $horario,
    ]);
}

it('no marca como pendiente un partido sin resultado que todavia no se jugo', function () {
    $this->travelTo(Carbon::parse('2026-05-01 08:00:00'));
    $contexto = crearContextoServicioResumen();
    $partidoFuturo = crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-02 09:00:00');

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['partidosPendientes']->firstWhere('id', $partidoFuturo->id))->toBeNull()
        ->and($resumen['pendientesDeCarga'])->toBe(0);
});

it('elige como proxima fecha la del proximo partido y no la ultima fecha creada', function () {
    $this->travelTo(Carbon::parse('2026-05-05 12:00:00'));
    $contexto = crearContextoServicioResumen();
    crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-03 09:00:00', 1, 0);
    $fechaSiguiente = Fecha::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha Siguiente']);
    crearPartidoServicioResumen($contexto, $fechaSiguiente, '2026-05-10 09:00:00');
    $fechaPosterior = Fecha::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha Posterior']);
    crearPartidoServicioResumen($contexto, $fechaPosterior, '2026-05-20 09:00:00');

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['proximaFecha']['id'])->toBe($fechaSiguiente->id)
        ->and($resumen['proximaFecha']['fecha'])->toBe('10/05/2026');
});

it('devuelve una proxima fecha nula cuando todos los partidos ya se jugaron', function () {
    $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));
    $contexto = crearContextoServicioResumen();
    crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-03 09:00:00', 2, 2);

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['proximaFecha'])->toBeNull();
});

it('ignora partidos futuros que ya tienen resultado y resume solo los que faltan jugar', function () {
    $this->travelTo(Carbon::parse('2026-05-05 12:00:00'));
    $contexto = crearContextoServicioResumen();
    crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-01 09:00:00', 1, 0);
    crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-07 00:00:00', 0, 0);
    $fechaSiguiente = Fecha::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha Siguiente']);
    crearPartidoServicioResumen($contexto, $fechaSiguiente, '2026-05-02 09:00:00', 2, 1);
    crearPartidoServicioResumen($contexto, $fechaSiguiente, '2026-05-10 10:00:00');
    crearPartidoServicioResumen($contexto, $fechaSiguiente, '2026-05-10 09:00:00');

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['proximaFecha']['id'])->toBe($fechaSiguiente->id)
        ->and($resumen['proximaFecha']['fecha'])->toBe('10/05/2026')
        ->and($resumen['proximaFecha']['cantidadPartidos'])->toBe(2)
        ->and($resumen['proximaFecha']['primerHorario'])->toBe('09:00');
});

it('toma la fecha en curso de cada categoria y no solo la ultima fecha creada', function () {
    $this->travelTo(Carbon::parse('2026-05-05 12:00:00'));
    $contexto = crearContextoServicioResumen();
    $pendienteCategoriaA = crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-03 09:00:00');

    $categoriaB = Categoria::create(['idEdicion' => $contexto['edicion']->id, 'nombreCategoria' => 'Sub 12']);
    $contextoB = ['categoria' => $categoriaB] + $contexto;
    $fechaB = Fecha::create(['idCategoria' => $categoriaB->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha B']);
    $pendienteCategoriaB = crearPartidoServicioResumen($contextoB, $fechaB, '2026-05-04 09:00:00');

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['partidosPendientes']->pluck('id')->all())
        ->toContain($pendienteCategoriaA->id, $pendienteCategoriaB->id);
});

it('sigue mostrando pendientes jugados aunque la ultima fecha creada sea futura', function () {
    $this->travelTo(Carbon::parse('2026-05-05 12:00:00'));
    $contexto = crearContextoServicioResumen();
    $pendienteJugado = crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-03 09:00:00');
    $fechaFutura = Fecha::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Fecha Futura']);
    crearPartidoServicioResumen($contexto, $fechaFutura, '2026-05-12 09:00:00');

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);

    expect($resumen['partidosPendientes']->pluck('id')->all())->toBe([$pendienteJugado->id]);
});

it('marca sin asistencia a un partido con goles confirmados y sin planilla, con accion a la planilla', function () {
    $this->travelTo(Carbon::parse('2026-05-05 12:00:00'));
    $contexto = crearContextoServicioResumen();
    $partido = crearPartidoServicioResumen($contexto, $contexto['fecha'], '2026-05-03 09:00:00', 2, 1);
    $partido->update(['goles_confirmados_at' => now()]);

    $resumen = crearServicioResumenDashboard()->obtenerResumen($contexto['edicion']->id);
    $pendiente = $resumen['partidosPendientes']->firstWhere('id', $partido->id);

    expect($pendiente['estado']['tipo'])->toBe('sin_asistencia')
        ->and($pendiente['accionUrl'])->toBe(route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $contexto['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0]));
});
