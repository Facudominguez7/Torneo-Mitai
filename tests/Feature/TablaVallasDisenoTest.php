<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;

function crearDatosTablaVallas(): array
{
    $edicion = Edicion::create(['nombre' => 'Edición Vallas']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 15']);
    foreach (['Equipo Uno' => 2, 'Equipo Dos' => 4, 'Equipo Tres' => 7] as $nombre => $goles) {
        $equipo = Equipo::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => $nombre, 'foto' => 'escudo.png']);
        EquipoEdicion::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'idEquipo' => $equipo->id, 'golesContra' => $goles]);
    }

    return [$edicion, $categoria];
}

it('muestra el podio defensivo y escudos reales junto a la clasificación', function () {
    [$edicion, $categoria] = crearDatosTablaVallas();
    $this->get(route('tabla-vallas', ['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id]))
        ->assertOk()->assertSee('Clasificación completa')->assertSee('1° puesto')->assertSee('Equipo Uno')
        ->assertSee('escudo.png')->assertSee('goles recibidos')->assertSee('Sub 15');
});

it('muestra todas las categorías al enviar el selector vacío', function () {
    [$edicion] = crearDatosTablaVallas();
    $this->get(route('tabla-vallas', ['idEdicion' => $edicion->id, 'idCategoria' => '']))
        ->assertOk()->assertSee('Equipo Uno')->assertSee('Equipo Tres');
});

it('conserva el orden alternativo y evita presentar ese orden como podio', function () {
    [$edicion] = crearDatosTablaVallas();
    $response = $this->get(route('tabla-vallas', ['idEdicion' => $edicion->id, 'sort' => 'equipo', 'dir' => 'desc']));
    $response->assertOk()->assertDontSee('1° puesto')->assertSeeInOrder(['Equipo Uno', 'Equipo Tres', 'Equipo Dos']);
});
