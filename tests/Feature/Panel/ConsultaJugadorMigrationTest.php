<?php

// Feature tests for the Consulta de jugador module migration onto the
// shared x-ui.* components (T7, panel-admin-redesign): the search form
// (DNI + idEdicion), the identity header, the "sin registros activos"
// warning, the stat tiles (Equipo, Categoría, Goles, Partidos jugados,
// Tarjetas rojas only when > 0), "Partidos donde hizo goles", the
// collapsible archived-history section and the full match history table.
// BajaJugadorTrazabilidadTest already covers the archived-history phrases
// ("Historial Archivado por Bajas", "No computa para ese equipo", "Goles
// archivados") against this same view and must keep passing unmodified.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminConsultaJugadorMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoConsultaJugadorMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Consulta Jugador Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 17 Migration',
    ]);
    $fecha = Fecha::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Fecha 1']);
    $equipoLocal = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Mitai Local', 'foto' => 'a.png']);
    $equipoVisitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Mitai Visitante', 'foto' => 'b.png']);
    EquipoEdicion::create(['idEdicion' => $edicion->id, 'idEquipo' => $equipoLocal->id, 'idCategoria' => $categoria->id]);
    EquipoEdicion::create(['idEdicion' => $edicion->id, 'idEquipo' => $equipoVisitante->id, 'idCategoria' => $categoria->id]);

    $partido = Partido::create([
        'idCategoria' => $categoria->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idEdicion' => $edicion->id,
        'golesEquipoLocal' => 3,
        'golesEquipoVisitante' => 1,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-05-10 10:00:00',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'equipoLocal', 'equipoVisitante', 'partido');
}

it('renderiza el formulario de busqueda por dni con el contexto de edicion', function () {
    $admin = crearAdminConsultaJugadorMigration();
    $contexto = crearContextoConsultaJugadorMigration();

    $response = $this->actingAs($admin)->get(route('jugador.consulta', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Consulta de Jugador')
        ->assertSee('id="dni"', false)
        ->assertSee(e(route('jugador.buscar')), false)
        ->assertSee($contexto['edicion']->nombre);
});

it('muestra el encabezado del jugador, las tarjetas estadisticas y los partidos con goles', function () {
    $admin = crearAdminConsultaJugadorMigration();
    $contexto = crearContextoConsultaJugadorMigration();

    Jugador::create(['dni' => 40400001, 'nombre' => 'Mateo', 'apellido' => 'Suarez']);
    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => Partido::class,
        'dni_jugador' => 40400001,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 9,
        'goles' => 2,
        'asistio' => true,
        'tarjeta_roja' => false,
    ]);

    $token = 'token-consulta-jugador-1';
    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), [
            '_token' => $token,
            'dni' => 40400001,
            'idEdicion' => $contexto['edicion']->id,
        ]);

    $response->assertOk()
        ->assertSee('Suarez, Mateo')
        ->assertSee('DNI: 40400001')
        ->assertSee('Mitai Local')
        ->assertSee($contexto['categoria']->nombreCategoria)
        ->assertSee('Goles')
        ->assertSee('Partidos Jugados')
        ->assertDontSee('Tarjetas Rojas')
        ->assertSee('Partidos donde Hizo Goles')
        ->assertSee('Historial Completo de Partidos');
});

it('muestra tarjetas rojas solo cuando hay al menos una', function () {
    $admin = crearAdminConsultaJugadorMigration();
    $contexto = crearContextoConsultaJugadorMigration();

    Jugador::create(['dni' => 40400002, 'nombre' => 'Juan', 'apellido' => 'Gomez']);
    PlanillaJugador::create([
        'partido_id' => $contexto['partido']->id,
        'partido_type' => Partido::class,
        'dni_jugador' => 40400002,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'numero_camiseta' => 4,
        'goles' => 0,
        'asistio' => true,
        'tarjeta_roja' => true,
    ]);

    $token = 'token-consulta-jugador-2';
    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), [
            '_token' => $token,
            'dni' => 40400002,
            'idEdicion' => $contexto['edicion']->id,
        ]);

    $response->assertOk()->assertSee('Tarjetas Rojas');
});

it('muestra la advertencia de sin registros activos cuando el jugador solo esta en lista de buena fe', function () {
    $admin = crearAdminConsultaJugadorMigration();
    $contexto = crearContextoConsultaJugadorMigration();

    Jugador::create(['dni' => 40400003, 'nombre' => 'Lucia', 'apellido' => 'Lopez']);
    ListaBuenaFe::create([
        'dni_jugador' => 40400003,
        'idEquipo' => $contexto['equipoLocal']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2011-01-01',
        'fecha_alta' => now(),
        'activo' => true,
    ]);

    $token = 'token-consulta-jugador-3';
    $response = $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), [
            '_token' => $token,
            'dni' => 40400003,
            'idEdicion' => $contexto['edicion']->id,
        ]);

    $response->assertOk()->assertSee('Sin registros activos de partidos');
});

it('redirige con error cuando el dni no existe', function () {
    $admin = crearAdminConsultaJugadorMigration();
    $contexto = crearContextoConsultaJugadorMigration();

    $token = 'token-consulta-jugador-4';
    $this->actingAs($admin)
        ->withSession(['_token' => $token])
        ->post(route('jugador.buscar'), ['_token' => $token, 'dni' => 99999999, 'idEdicion' => $contexto['edicion']->id])
        ->assertRedirect(route('jugador.consulta', ['dni' => 99999999, 'idEdicion' => $contexto['edicion']->id]));

    $this->actingAs($admin)
        ->get(route('jugador.consulta', ['dni' => 99999999, 'idEdicion' => $contexto['edicion']->id]))
        ->assertOk();
});
