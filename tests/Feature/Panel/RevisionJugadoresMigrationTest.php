<?php

// Feature tests for the Revisión de jugadores module migration onto the
// shared x-ui.* components (T7, panel-admin-redesign): the
// Pendientes/Resueltos tabs, the search/edición/equipo filters, the table
// with motivo_conflicto and, for resueltos, resuelto_por/fecha_resolucion,
// and the "Resolver" modal with notas_resolucion. Every route, input name
// and JS id is kept intact.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\User;

function crearAdminRevisionJugadoresMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoRevisionJugadoresMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Revision Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 16 Migration',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreEquipo'] ?? 'Mitai Revision',
        'foto' => 'escudo.png',
    ]);
    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('edicion', 'categoria', 'equipo');
}

function crearJugadorConflictivoMigration(array $contexto, string $dni, array $atributos = []): ListaBuenaFe
{
    Jugador::firstOrCreate(['dni' => $dni], ['nombre' => 'Mateo', 'apellido' => 'Suarez']);

    return ListaBuenaFe::create(array_merge([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2011-05-10',
        'fecha_alta' => now()->subDays(2),
        'activo' => true,
        'conflictivo' => true,
        'motivo_conflicto' => 'Posible duplicado con otro equipo',
        'resuelto' => false,
    ], $atributos));
}

it('renderiza el encabezado, las tabs y los filtros de revision de jugadores', function () {
    $admin = crearAdminRevisionJugadoresMigration();
    $contexto = crearContextoRevisionJugadoresMigration();
    crearJugadorConflictivoMigration($contexto, '40300001');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos'));

    $response->assertOk()
        ->assertSee('Revisión de jugadores')
        ->assertSee('Pendientes')
        ->assertSee('Resueltos')
        ->assertSee('name="search"', false)
        ->assertSee('name="idEdicion"', false)
        ->assertSee('name="idEquipo"', false)
        ->assertSee('Posible duplicado con otro equipo')
        ->assertSee('<h1', false);
});

it('muestra resuelto por y fecha de resolucion solo en la pestaña resueltos', function () {
    $admin = crearAdminRevisionJugadoresMigration();
    $contexto = crearContextoRevisionJugadoresMigration();
    crearJugadorConflictivoMigration($contexto, '40300002', [
        'resuelto' => true,
        'resuelto_por' => 'Admin Torneo',
        'fecha_resolucion' => '2026-09-20 15:30:00',
        'notas_resolucion' => 'Se verificó la identidad',
    ]);
    crearJugadorConflictivoMigration($contexto, '40300009');

    $resueltos = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos', ['estado' => 'resueltos']));

    $resueltos->assertOk()
        ->assertSee('Resuelto Por')
        ->assertSee('Fecha Resolución')
        ->assertSee('Admin Torneo')
        ->assertSee('20/09/2026 15:30')
        ->assertSee('Se verificó la identidad');

    $pendientes = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos'));

    $pendientes->assertOk()
        ->assertDontSee('Resuelto Por')
        ->assertDontSee('Fecha Resolución');
});

it('muestra el boton resolver solo para pendientes con los data attributes del modal', function () {
    $admin = crearAdminRevisionJugadoresMigration();
    $contexto = crearContextoRevisionJugadoresMigration();
    $registro = crearJugadorConflictivoMigration($contexto, '40300003');

    $response = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos'));

    $response->assertOk()
        ->assertSee('data-resolve-id="'.$registro->id.'"', false)
        ->assertSee('openResolveModalFromButton(', false)
        ->assertSee('resolveModal', false)
        ->assertSee('name="notas_resolucion"', false)
        ->assertSee('Marcar como Resuelto')
        ->assertSee('onclick="closeResolveModal()"', false);

    $registro->forceFill(['resuelto' => true, 'resuelto_por' => 'Admin Torneo', 'fecha_resolucion' => now()])->save();

    $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos', ['estado' => 'resueltos']))
        ->assertOk()
        ->assertSee('Admin Torneo')
        ->assertDontSee('data-resolve-id="'.$registro->id.'"', false);
});

it('renderiza el estado vacio de pendientes y de resueltos', function () {
    $admin = crearAdminRevisionJugadoresMigration();

    $pendientes = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos'));
    $pendientes->assertOk()->assertSee('No hay jugadores marcados como conflictivos pendientes');

    $resueltos = $this->actingAs($admin)->get(route('lista-buena-fe.conflictivos', ['estado' => 'resueltos']));
    $resueltos->assertOk()->assertSee('No hay casos resueltos para mostrar');
});
