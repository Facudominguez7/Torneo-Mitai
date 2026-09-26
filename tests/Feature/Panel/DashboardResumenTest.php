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
use App\Models\User;
use Illuminate\Support\Carbon;

function crearAdminDashboard(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoDashboard(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Dashboard']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 3',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

function crearEquipoDashboard(array $contexto, string $nombre): Equipo
{
    return Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);
}

it('muestra el estado vacio accesible cuando no hay edicion seleccionada', function () {
    $admin = crearAdminDashboard();

    $response = $this->actingAs($admin)->get(route('admin'));

    $response->assertOk()
        ->assertSee('Seleccioná una edición para ver el resumen del panel.')
        ->assertDontSee('Equipos inscriptos');
});

it('calcula las tarjetas KPI del dashboard con datos de la edicion', function () {
    $admin = crearAdminDashboard();
    $contexto = crearContextoDashboard();

    $equipoDirecto = crearEquipoDashboard($contexto, 'Mitai Directo');
    $equipoPivote = crearEquipoDashboard($contexto, 'Mitai Pivote');
    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipoPivote->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    foreach ([['30111111', true], ['30222222', true], ['30333333', true], ['30444444', false]] as [$dni, $activo]) {
        Jugador::create(['dni' => $dni, 'nombre' => 'Jugador', 'apellido' => $dni]);
        ListaBuenaFe::create([
            'dni_jugador' => $dni,
            'idEquipo' => $equipoDirecto->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'fecha_nacimiento' => '2015-01-01',
            'fecha_alta' => now(),
            'activo' => $activo,
            'fecha_baja' => $activo ? null : now(),
        ]);
    }

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $equipoDirecto->id,
        'idEquipoVisitante' => $equipoPivote->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'goles_confirmados_at' => now(),
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-04-01 10:00:00',
    ]);

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $equipoPivote->id,
        'idEquipoVisitante' => $equipoDirecto->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-04-01 11:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Equipos inscriptos')
        ->assertSee('Jugadores habilitados')
        ->assertSee('Partidos jugados')
        ->assertSee('1 / 2', false)
        ->assertSee('Pendientes de carga');
});

it('lista los partidos que requieren atencion con su badge y accion', function () {
    $admin = crearAdminDashboard();
    $contexto = crearContextoDashboard();
    $local = crearEquipoDashboard($contexto, 'Mitai Sin Resultado');
    $visitante = crearEquipoDashboard($contexto, 'Mitai Rival Uno');

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
        'horario_datetime' => '2026-04-02 09:00:00',
    ]);

    $localGoles = crearEquipoDashboard($contexto, 'Mitai Goles Sin Confirmar');
    $visitanteGoles = crearEquipoDashboard($contexto, 'Mitai Rival Dos');
    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $localGoles->id,
        'idEquipoVisitante' => $visitanteGoles->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 3,
        'golesEquipoVisitante' => 2,
        'goles_confirmados_at' => null,
        'cancha' => 2,
        'jugado' => 1,
        'horario_datetime' => '2026-04-02 10:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Requiere atención')
        ->assertSee('Mitai Sin Resultado')
        ->assertSee('Sin resultado')
        ->assertSee('Goles sin confirmar')
        ->assertSee(e(route('cargar-resultado', ['idPartido' => $partidoSinResultado->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idGrupo' => $contexto['grupo']->id, 'idFecha' => $contexto['fecha']->id])), false)
        ->assertSee('Ver todos');
});

it('muestra la proxima fecha con cantidad de partidos canchas y primer horario', function () {
    $this->travelTo(Carbon::parse('2026-04-01 12:00:00'));
    $admin = crearAdminDashboard();
    $contexto = crearContextoDashboard();
    $local = crearEquipoDashboard($contexto, 'Mitai Fecha Local');
    $visitante = crearEquipoDashboard($contexto, 'Mitai Fecha Visitante');

    Partido::create([
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
        'horario_datetime' => '2026-04-05 09:30:00',
    ]);

    Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $visitante->id,
        'idEquipoVisitante' => $local->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'cancha' => 2,
        'jugado' => 0,
        'horario_datetime' => '2026-04-05 11:00:00',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Próxima fecha')
        ->assertSee('Fecha 3')
        ->assertSee('09:30')
        ->assertSee(e(route('partido.index', ['idEdicion' => $contexto['edicion']->id, 'idFecha' => $contexto['fecha']->id])), false);
});

it('preserva las acciones existentes y el listado completo colapsado con el filtro de horario', function () {
    $admin = crearAdminDashboard();
    $contexto = crearContextoDashboard();

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Notificaciones')
        ->assertSee('Partidos')
        ->assertSee('Imprimir Tablas de Posiciones')
        ->assertSee('Imprimir Fixture')
        ->assertSee('Planilla de Cobro')
        ->assertSee('Todos los partidos de la edición')
        ->assertSee('id="horario"', false)
        ->assertSee('<details', false);
});

it('muestra un estado vacio de proxima fecha cuando no quedan partidos por jugar', function () {
    $admin = crearAdminDashboard();
    $contexto = crearContextoDashboard();

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Próxima fecha')
        ->assertSee('No hay partidos programados')
        ->assertSee(e(route('partido.index', ['idEdicion' => $contexto['edicion']->id])), false);
});
