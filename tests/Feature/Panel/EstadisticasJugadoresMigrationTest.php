<?php

// Feature tests for the Estadisticas de jugadores module migration onto the
// shared x-ui.* components (T8a, panel-admin-redesign). The controller is
// untouched: this only re-skins the existing KPI counters, the per-edicion
// counts, the edicion + busqueda filters, the expandable per-edicion detail
// rows (kept with their original ids/JS hook) and pagination.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminEstadisticasJugadoresMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoEstadisticasJugadoresMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Estadisticas Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Migration',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreEquipo'] ?? 'Mitai Verde',
        'foto' => 'escudo.png',
    ]);
    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

function crearJugadorActivoEstadisticasMigration(array $contexto, string $dni, string $nombre, string $apellido): Jugador
{
    $jugador = Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
    ]);

    ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2013-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => true,
    ]);

    return $jugador;
}

function crearPartidoJugadoEstadisticasMigration(array $contexto, string $dni, int $goles = 0, int $tarjetasRojas = 0): void
{
    PlanillaJugador::create([
        'dni_jugador' => $dni,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idCategoria' => $contexto['categoria']->id,
        'partido_id' => 1,
        'partido_type' => Partido::class,
        'asistio' => true,
        'goles' => $goles,
        'tarjeta_roja' => $tarjetasRojas,
    ]);
}

it('muestra las tarjetas kpi que ya calcula el controlador', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000001', 'Mateo', 'Duarte');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000001', 3);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('Jugadores activos')
        ->assertSee('Con 5 partidos o menos')
        ->assertSee('Con más de 5 partidos')
        ->assertSee('Estadísticas de jugadores');
});

it('conserva el filtro de edicion y la busqueda por nombre apellido o dni', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000010', 'Bruno', 'Sosa');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000010', 1);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'sosa',
    ]));

    $response->assertOk()
        ->assertSee('id="idEdicion"', false)
        ->assertSee('name="busqueda"', false)
        ->assertSee('value="sosa"', false)
        ->assertSee('Sosa, Bruno')
        ->assertSee('DNI: 41000010');
});

it('conserva el detalle por edicion con sus ids y el hook de js', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000020', 'Lucas', 'Cabrera');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000020', 2);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('id="detalles-41000020"', false)
        ->assertSee('id="icon-41000020"', false)
        ->assertSee("toggleDetalles('detalles-41000020')", false)
        ->assertSee('function toggleDetalles(id)', false)
        ->assertSee($contexto['edicion']->nombre);
});

it('conserva el conteo por edicion como chip navegable', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000030', 'Santino', 'Ortiz');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000030', 0);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee(e(route('estadisticas-jugadores', ['idEdicion' => $contexto['edicion']->id])), false)
        ->assertSee('aria-current="page"', false)
        ->assertSee($contexto['edicion']->nombre);
});

it('renderiza el estado vacio especifico de busqueda sin resultados', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'Inexistente',
    ]));

    $response->assertOk()
        ->assertSee('No se encontraron jugadores')
        ->assertSee('No hay jugadores que coincidan con la búsqueda "Inexistente".', false);
});

it('conserva la paginacion existente', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();

    foreach (range(1, 25) as $i) {
        $dni = (string) (41100000 + $i);
        crearJugadorActivoEstadisticasMigration($contexto, $dni, 'Jugador'.$i, 'Comun');
        crearPartidoJugadoEstadisticasMigration($contexto, $dni, 1);
    }

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()->assertSee('Mostrando');
});
