<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;

function crearAdminParaEliminarEquipo(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoTablaPosiciones(string $nombreEdicion, string $nombreCategoria, string $nombreGrupo): array
{
    $edicion = Edicion::create([
        'nombre' => $nombreEdicion,
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $nombreCategoria,
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombreGrupo,
    ]);

    return compact('edicion', 'categoria', 'grupo');
}

function crearEquipoConEdicion(array $contexto, string $nombreEquipo): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombreEquipo,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'golesContra' => 0,
    ]);

    return $equipo;
}

it('elimina la posicion de tabla de la misma edicion al borrar un equipo', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contextoPrincipal = crearContextoTablaPosiciones('Edicion Principal', 'Sub 12', 'Grupo A');
    $contextoAjeno = crearContextoTablaPosiciones('Edicion Ajena', 'Sub 13', 'Grupo B');
    $equipo = crearEquipoConEdicion($contextoPrincipal, 'Mitai Azul');

    TablaPosicion::create([
        'idGrupo' => $contextoPrincipal['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoPrincipal['edicion']->id,
        'puntos' => 9,
        'golesFavor' => 8,
        'golesContra' => 2,
        'diferenciaGoles' => 6,
        'jugado' => 3,
        'ganado' => 3,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contextoAjeno['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
        'puntos' => 4,
        'golesFavor' => 3,
        'golesContra' => 5,
        'diferenciaGoles' => -2,
        'jugado' => 2,
        'ganado' => 1,
        'perdido' => 1,
        'empatado' => 0,
    ]);

    $response = $this->actingAs($admin)->delete(route('equipo.destroy', [
        'equipo' => $equipo,
        'idEdicion' => $contextoPrincipal['edicion']->id,
    ]));

    $response->assertRedirect(route('equipo.index', ['idEdicion' => $contextoPrincipal['edicion']->id]));

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoPrincipal['edicion']->id,
    ]);

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
    ]);
});

it('no borra posiciones ajenas cuando el equipo no tiene coincidencia en la edicion actual', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contextoPrincipal = crearContextoTablaPosiciones('Edicion Principal', 'Sub 14', 'Grupo C');
    $contextoAjeno = crearContextoTablaPosiciones('Edicion Ajena', 'Sub 15', 'Grupo D');
    $equipoPrincipal = crearEquipoConEdicion($contextoPrincipal, 'Mitai Verde');
    $equipoAjeno = crearEquipoConEdicion($contextoAjeno, 'Mitai Rojo');

    TablaPosicion::create([
        'idGrupo' => $contextoPrincipal['grupo']->id,
        'idEquipo' => $equipoPrincipal->id,
        'idEdicion' => $contextoPrincipal['edicion']->id,
        'puntos' => 7,
        'golesFavor' => 5,
        'golesContra' => 1,
        'diferenciaGoles' => 4,
        'jugado' => 3,
        'ganado' => 2,
        'perdido' => 0,
        'empatado' => 1,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contextoAjeno['grupo']->id,
        'idEquipo' => $equipoAjeno->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
        'puntos' => 10,
        'golesFavor' => 11,
        'golesContra' => 3,
        'diferenciaGoles' => 8,
        'jugado' => 4,
        'ganado' => 3,
        'perdido' => 0,
        'empatado' => 1,
    ]);

    $response = $this->actingAs($admin)->delete(route('equipo.destroy', [
        'equipo' => $equipoPrincipal,
        'idEdicion' => $contextoPrincipal['edicion']->id,
    ]));

    $response->assertRedirect(route('equipo.index', ['idEdicion' => $contextoPrincipal['edicion']->id]));

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEquipo' => $equipoAjeno->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
    ]);
});
