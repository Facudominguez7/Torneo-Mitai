<?php

// Feature tests for the Instituciones module migration onto the shared
// x-ui.* components (T7, panel-admin-redesign): counters, the edition
// filter, the inline create form, "Equipos sin institución" with the
// select-to-link action, and the per-institution cards with move/unlink
// actions and the inline rename toggle (kept as JS, ids preserved). The
// desvincular-equipo route is registered as POST only (no matching DELETE
// route), so x-ui.confirm-delete (which spoofs DELETE) cannot be used
// there without a route change out of this task's scope; the native
// window.confirm() step is kept, which already satisfies "destructive
// action asks for confirmation".

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Institucion;
use App\Models\User;
use Illuminate\Support\Str;

function crearAdminInstitucionMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoInstitucionMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Institucion Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 18 Migration',
    ]);

    return compact('edicion', 'categoria');
}

it('renderiza el encabezado con los contadores y el filtro de edicion', function () {
    $admin = crearAdminInstitucionMigration();
    $contexto = crearContextoInstitucionMigration();
    Institucion::create(['nombre' => 'Escuela Uno Migration']);

    $response = $this->actingAs($admin)->get(route('institucion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Gestión de Instituciones')
        ->assertSee('Instituciones')
        ->assertSee('Con equipos')
        ->assertSee('Sin institución')
        ->assertSee('name="idEdicion"', false)
        ->assertSee('text-2xl font-bold text-gray-800', false);
});

it('renderiza el formulario inline de creacion de institucion', function () {
    $admin = crearAdminInstitucionMigration();

    $response = $this->actingAs($admin)->get(route('institucion.index'));

    $response->assertOk()
        ->assertSee('Crear Nueva Institución')
        ->assertSee(e(route('institucion.store')), false)
        ->assertSee('name="nombre"', false);
});

it('muestra los equipos sin institucion con el selector para vincularlos', function () {
    $admin = crearAdminInstitucionMigration();
    $contexto = crearContextoInstitucionMigration();
    $institucion = Institucion::create(['nombre' => 'Escuela Dos Migration']);
    // Equipo::creating() auto-asigna idInstitucion por nombre normalizado
    // (NormalizadorInstituciones), así que un alta nueva siempre queda
    // vinculada; se fuerza null después para representar el caso real que
    // esta pantalla administra: equipos legacy sin institución.
    $equipoSinInstitucion = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Equipo Sin Institucion Migration',
        'foto' => 'a.png',
    ]);
    $equipoSinInstitucion->forceFill(['idInstitucion' => null])->save();

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipoSinInstitucion->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response = $this->actingAs($admin)->get(route('institucion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Equipos sin Institución')
        ->assertSee('Equipo Sin Institucion Migration')
        ->assertSee(e(route('institucion.cambiarEquipo')), false)
        ->assertSee(Str::limit($institucion->nombre, 18));
});

it('renderiza las tarjetas de institucion con mover, desvincular y el toggle de renombrar', function () {
    $admin = crearAdminInstitucionMigration();
    $contexto = crearContextoInstitucionMigration();
    $institucion = Institucion::create(['nombre' => 'Escuela Tres Migration']);
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Equipo Con Institucion Migration',
        'foto' => 'a.png',
        'idInstitucion' => $institucion->id,
    ]);
    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    $response = $this->actingAs($admin)->get(route('institucion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Equipo Con Institucion Migration')
        ->assertSee('id="edit-form-'.$institucion->id.'"', false)
        ->assertSee('toggleEdit(', false)
        ->assertSee(e(route('institucion.update', $institucion)), false)
        ->assertSee(e(route('institucion.desvincularEquipo')), false)
        ->assertSee('Desvincular de institución');
});

it('renderiza el estado vacio cuando no hay instituciones registradas', function () {
    $admin = crearAdminInstitucionMigration();

    $response = $this->actingAs($admin)->get(route('institucion.index'));

    $response->assertOk()->assertSee('No hay instituciones registradas');
});
