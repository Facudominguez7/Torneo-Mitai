<?php

// Regresión del botón "Agregar Equipo de una Edicion Anterior": el link
// llegaba con "&amp;amp;tipo=" (doble escape en x-ui.button), el navegador
// perdía el parámetro tipo y seleccionar-edicion daba 500 al armar
// route('.create').

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\User;

function crearAdminOtraEdicion(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

it('el boton del listado de equipos conserva el parametro tipo', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Destino Boton']);

    $html = $this->actingAs(crearAdminOtraEdicion())
        ->get(route('equipo.index', ['idEdicion' => $edicion->id]))
        ->assertOk()
        ->getContent();

    $esperado = e(route('seleccionar-edicion', ['idEdicion' => $edicion->id, 'tipo' => 'equipoedicion']));

    expect($html)->toContain('href="'.$esperado.'"')
        ->not->toContain('&amp;amp;tipo=');
});

it('seleccionar edicion con tipo equipoedicion apunta al alta de equipos por edicion', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Destino Tipo']);

    $this->actingAs(crearAdminOtraEdicion())
        ->get(route('seleccionar-edicion', ['idEdicion' => $edicion->id, 'tipo' => 'equipoedicion']))
        ->assertOk()
        ->assertSee('action="'.route('equipoedicion.create').'"', false);
});

it('seleccionar edicion sin tipo o con un tipo invalido no da 500 y usa equipoedicion', function (?string $tipo) {
    $edicion = Edicion::create(['nombre' => 'Edicion Destino Sin Tipo']);

    $parametros = ['idEdicion' => $edicion->id];
    if ($tipo !== null) {
        $parametros['tipo'] = $tipo;
    }

    $this->actingAs(crearAdminOtraEdicion())
        ->get(route('seleccionar-edicion', $parametros))
        ->assertOk()
        ->assertSee('action="'.route('equipoedicion.create').'"', false);
})->with([
    'sin tipo' => [null],
    'tipo vacio' => [''],
    'tipo inexistente' => ['no-existe'],
]);

it('el alta asocia el equipo de la edicion anterior a la categoria equivalente de la nueva', function () {
    $origen = Edicion::create(['nombre' => 'Edicion Origen Alta']);
    $destino = Edicion::create(['nombre' => 'Edicion Destino Alta']);
    $categoriaOrigen = Categoria::create(['idEdicion' => $origen->id, 'nombreCategoria' => 'Categoría 2015']);
    $categoriaDestino = Categoria::create(['idEdicion' => $destino->id, 'nombreCategoria' => 'Categoria 2015']);
    $equipo = Equipo::create(['idCategoria' => $categoriaOrigen->id, 'idEdicion' => $origen->id, 'nombre' => 'Equipo Que Vuelve', 'foto' => 'e.png']);

    $this->actingAs(crearAdminOtraEdicion())
        ->withSession(['_token' => 'token-otra-edicion'])
        ->post(route('equipoedicion.store'), [
            '_token' => 'token-otra-edicion',
            'idEquipo' => $equipo->id,
            'idEdicion' => $destino->id,
            'edicionfiltro' => $origen->id,
            'idCategoria' => $categoriaOrigen->id,
        ])
        ->assertRedirect(route('equipo.index', ['idEdicion' => $destino->id]))
        ->assertSessionHasNoErrors();

    expect(EquipoEdicion::where('idEquipo', $equipo->id)->where('idEdicion', $destino->id)->value('idCategoria'))
        ->toBe($categoriaDestino->id);
});

// Regresión visual: estas pantallas conservaban labels text-white y ayudas
// indigo del tema oscuro anterior, invisibles sobre el fondo claro del panel.
it('seleccionar edicion muestra labels legibles sobre el fondo claro del panel', function () {
    $edicion = Edicion::create(['nombre' => "Edicion Mita'i Legible"]);

    $html = $this->actingAs(crearAdminOtraEdicion())
        ->get(route('seleccionar-edicion', ['idEdicion' => $edicion->id, 'tipo' => 'equipoedicion']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('for="buscarEquipoGlobal" class="mb-1 block text-sm font-medium text-gray-700"')
        ->toContain('for="edicionfiltro" class="mb-1 block text-sm font-medium text-gray-700"')
        ->toContain('for="idCategoria" class="mb-1 block text-sm font-medium text-gray-700"')
        ->toContain(e("Edición destino: Edicion Mita'i Legible (ID {$edicion->id})"))
        ->not->toContain('&amp;#039;')
        ->not->toContain('indigo-')
        ->not->toContain('font-medium text-white mb-');
});

it('el alta de equipo de otra edicion muestra labels legibles y el boton con la marca', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Destino Alta Legible']);

    $html = $this->actingAs(crearAdminOtraEdicion())
        ->get(route('equipoedicion.create', ['idEdicion' => $edicion->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('for="idEquipo" class="mb-1 block text-sm font-medium text-gray-700"')
        ->toContain('bg-brand-navy text-white')
        ->not->toContain('indigo-')
        ->not->toContain('panel-form-submit')
        ->not->toContain('font-medium text-white">');
});
