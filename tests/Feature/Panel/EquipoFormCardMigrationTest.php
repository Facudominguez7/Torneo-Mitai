<?php

// Feature tests for the Equipos create/edit form migration onto
// x-ui.form-card (T3, panel-admin-redesign). Parity: same fields, same
// routes, same file input for the logo — only the wrapper markup and label
// color change (text-white was invisible on the light shell since T1).

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\User;

function crearAdminEquipoFormCard(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('renderiza el formulario de alta de equipo dentro de x-ui.form-card', function () {
    $admin = crearAdminEquipoFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Form Alta']);
    Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 13']);

    $response = $this->actingAs($admin)->get(route('equipo.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('type="file"', false)
        ->assertSee('name="foto"', false)
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('mantiene el input de logo y el select de categoría en el alta de equipo', function () {
    $admin = crearAdminEquipoFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Form Campos']);
    Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);

    $response = $this->actingAs($admin)->get(route('equipo.create', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertSee('name="idCategoria"', false)
        ->assertSee('Sub 14')
        ->assertSee('focus:border-brand-navy', false);
});

it('sigue permitiendo crear un equipo con el mismo contrato de datos', function () {
    $admin = crearAdminEquipoFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Form Store']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 15']);

    // El contenedor de testing expone APP_ENV=local, así que el middleware
    // CSRF valida de verdad (ver PartidoCrudTest::cabecerasCsrf); se inyecta
    // el token explícitamente para la petición mutante.
    $response = $this->actingAs($admin)
        ->withSession(['_token' => 'token-csrf-equipo-formcard'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-equipo-formcard'])
        ->post(route('equipo.store'), [
            'nombre' => 'Mitai Celeste',
            'idCategoria' => $categoria->id,
            'idEdicion' => $edicion->id,
        ]);

    $response->assertRedirect(route('equipo.index', ['idEdicion' => $edicion->id]));
    expect(Equipo::where('nombre', 'Mitai Celeste')->exists())->toBeTrue();
});

it('renderiza el formulario de edición de equipo dentro de x-ui.form-card sin labels blancos', function () {
    $admin = crearAdminEquipoFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Form Edit']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Gris',
        'foto' => 'escudo.png',
    ]);

    $response = $this->actingAs($admin)->get(route('equipo.edit', $equipo->id));

    $response->assertOk()
        ->assertSee('max-w-3xl', false)
        ->assertSee('Mitai Gris')
        ->assertDontSee('block text-base font-medium text-white', false);
});

it('renderiza el detalle de equipo con encabezado legible sobre fondo claro', function () {
    $admin = crearAdminEquipoFormCard();
    $edicion = Edicion::create(['nombre' => 'Edicion Form Show']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 17']);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Violeta',
        'foto' => 'escudo.png',
    ]);

    $response = $this->actingAs($admin)->get(route('equipo.show', [
        'equipo' => $equipo->id,
        'idEdicion' => $edicion->id,
    ]));

    $response->assertOk()
        ->assertSee('Detalle de Equipo')
        ->assertDontSee('block text-base font-medium text-white', false);
});
