<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\EquipoGrupo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;
use Illuminate\Support\Str;

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

function filaTablaEliminar(array $contexto, Equipo $equipo, int $idEdicionFila): TablaPosicion
{
    return TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $idEdicionFila,
        'puntos' => 9,
        'golesFavor' => 8,
        'golesContra' => 2,
        'diferenciaGoles' => 6,
        'jugado' => 3,
        'ganado' => 3,
        'perdido' => 0,
        'empatado' => 0,
    ]);
}

function eliminarEquipoComoAdmin(object $test, User $admin, Equipo $equipo, int $idEdicion): void
{
    $token = Str::random(40);

    $test->actingAs($admin)
        ->withSession(['_token' => $token])
        ->delete(route('equipo.destroy', [
            'equipo' => $equipo,
            'idEdicion' => $idEdicion,
        ]), ['_token' => $token]);
}

it('elimina la posicion de tabla de la misma edicion al borrar un equipo', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contextoPrincipal = crearContextoTablaPosiciones('Edicion Principal', 'Sub 12', 'Grupo A');
    $contextoAjeno = crearContextoTablaPosiciones('Edicion Ajena', 'Sub 13', 'Grupo B');
    $equipo = crearEquipoConEdicion($contextoPrincipal, 'Mitai Azul');
    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
        'idCategoria' => $contextoAjeno['categoria']->id,
    ]);

    EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $contextoPrincipal['grupo']->id]);
    EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $contextoAjeno['grupo']->id]);

    filaTablaEliminar($contextoPrincipal, $equipo, $contextoPrincipal['edicion']->id);
    filaTablaEliminar($contextoAjeno, $equipo, $contextoAjeno['edicion']->id);

    eliminarEquipoComoAdmin($this, $admin, $equipo, $contextoPrincipal['edicion']->id);

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contextoPrincipal['grupo']->id,
    ]);

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
    ]);
});

it('no borra posiciones ajenas cuando el equipo no tiene coincidencia en la edicion actual', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contextoPrincipal = crearContextoTablaPosiciones('Edicion Principal Dos', 'Sub 14', 'Grupo C');
    $contextoAjeno = crearContextoTablaPosiciones('Edicion Ajena Dos', 'Sub 15', 'Grupo D');
    $equipoPrincipal = crearEquipoConEdicion($contextoPrincipal, 'Mitai Verde');
    $equipoAjeno = crearEquipoConEdicion($contextoAjeno, 'Mitai Rojo');

    filaTablaEliminar($contextoPrincipal, $equipoPrincipal, $contextoPrincipal['edicion']->id);
    filaTablaEliminar($contextoAjeno, $equipoAjeno, $contextoAjeno['edicion']->id);

    eliminarEquipoComoAdmin($this, $admin, $equipoPrincipal, $contextoPrincipal['edicion']->id);

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEquipo' => $equipoAjeno->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
    ]);
});

it('elimina las filas legacy con idEdicion en cero del grupo de la edicion actual', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contexto = crearContextoTablaPosiciones('Edicion Legacy Baja', 'Sub 11', 'Grupo Unico');
    $equipo = crearEquipoConEdicion($contexto, 'Mitai Legacy');

    filaTablaEliminar($contexto, $equipo, 0);

    eliminarEquipoComoAdmin($this, $admin, $equipo, $contexto['edicion']->id);

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contexto['grupo']->id,
    ]);
});

it('saca al equipo de la edicion y del grupo sin tocar sus grupos de otras ediciones', function () {
    $admin = crearAdminParaEliminarEquipo();
    $contextoPrincipal = crearContextoTablaPosiciones('Edicion Actual', 'Sub 10', 'Grupo Actual');
    $contextoAjeno = crearContextoTablaPosiciones('Edicion Otra', 'Sub 9', 'Grupo Otra');
    $equipo = crearEquipoConEdicion($contextoPrincipal, 'Mitai Naranja');
    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
        'idCategoria' => $contextoAjeno['categoria']->id,
    ]);

    EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $contextoPrincipal['grupo']->id]);
    EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $contextoAjeno['grupo']->id]);

    eliminarEquipoComoAdmin($this, $admin, $equipo, $contextoPrincipal['edicion']->id);

    $this->assertDatabaseMissing('equipo_ediciones', [
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoPrincipal['edicion']->id,
    ]);

    $this->assertDatabaseMissing('equipos_grupos', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contextoPrincipal['grupo']->id,
    ]);

    $this->assertDatabaseHas('equipo_ediciones', [
        'idEquipo' => $equipo->id,
        'idEdicion' => $contextoAjeno['edicion']->id,
    ]);

    $this->assertDatabaseHas('equipos_grupos', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contextoAjeno['grupo']->id,
    ]);
});
