<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

const TIPO_PARTIDO_PANEL = 'App\\Models\\Partido';

function crearAdminPanel(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoBaseAdmin(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edicion Test',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha 7',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

function crearEquipoEnEdicion(array $contexto, string $nombre): Equipo
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

function crearPartidoAdmin(array $contexto, Equipo $local, Equipo $visitante, string $horario): Partido
{
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 0,
        'horario_datetime' => $horario,
    ]);
}

function crearJugadorConPlanilla(Partido $partido, Equipo $equipo, Categoria $categoria, Edicion $edicion, int $dni, bool $asistio): void
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => 'Jugador',
        'apellido' => (string) $dni,
    ]);

    PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => TIPO_PARTIDO_PANEL,
        'dni_jugador' => $dni,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'goles' => 0,
        'asistio' => $asistio,
        'tarjeta_roja' => false,
    ]);
}

it('muestra un equipo pendiente cuando no tiene planilla cargada', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Azul');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Rojo');

    crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 10:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Equipos con asistencia pendiente')
        ->assertSee('Mitai Azul')
        ->assertSee('Mitai Rojo')
        ->assertSee('Sin planilla');
});

it('mantiene pendiente a un equipo cuando la planilla existe pero nadie asistio', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Verde');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Blanco');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 11:00:00');

    crearJugadorConPlanilla($partido, $local, $contexto['categoria'], $contexto['edicion'], 30100111, false);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Mitai Verde')
        ->assertSee('Sin asistencias marcadas');
});

it('oculta del reporte a un equipo que ya tiene al menos una asistencia marcada', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();
    $local = crearEquipoEnEdicion($contexto, 'Mitai Dorado');
    $visitante = crearEquipoEnEdicion($contexto, 'Mitai Negro');
    $partido = crearPartidoAdmin($contexto, $local, $visitante, '2026-03-15 12:00:00');

    crearJugadorConPlanilla($partido, $local, $contexto['categoria'], $contexto['edicion'], 30100222, true);
    crearJugadorConPlanilla($partido, $visitante, $contexto['categoria'], $contexto['edicion'], 30100223, true);

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertDontSee('Sin planilla')
        ->assertDontSee('Sin asistencias marcadas')
        ->assertSee('No hay equipos con asistencias pendientes en el contexto visible del panel admin.');
});

it('respeta el filtro horario al mostrar los pendientes', function () {
    $admin = crearAdminPanel();
    $contexto = crearContextoBaseAdmin();

    $equipoManana = crearEquipoEnEdicion($contexto, 'Mitai Manana');
    $rivalManana = crearEquipoEnEdicion($contexto, 'Mitai Sol');
    crearPartidoAdmin($contexto, $equipoManana, $rivalManana, '2026-03-15 09:00:00');

    $equipoTarde = crearEquipoEnEdicion($contexto, 'Mitai Tarde');
    $rivalTarde = crearEquipoEnEdicion($contexto, 'Mitai Luna');
    crearPartidoAdmin($contexto, $equipoTarde, $rivalTarde, '2026-03-15 15:00:00');

    $response = $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id, 'horario' => '15:00']));

    $response->assertOk()
        ->assertSee('Horario 15:00 hs')
        ->assertSee('Mitai Tarde')
        ->assertDontSee('Mitai Manana');
});
