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

function adminParaQuitarDelGrupo(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function contextoQuitarDelGrupo(string $nombreEdicion): array
{
    $edicion = Edicion::create(['nombre' => $nombreEdicion]);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 8',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Unico',
    ]);

    return compact('edicion', 'categoria', 'grupo');
}

function equipoEnGrupo(array $contexto, string $nombreEquipo): Equipo
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
    ]);

    return $equipo;
}

function quitarEquipoDelGrupo(object $test, User $admin, EquipoGrupo $asignacion, int $idEdicion): void
{
    $token = Str::random(40);

    $test->actingAs($admin)
        ->withSession(['_token' => $token])
        ->delete(route('equipogrupo.destroy', $asignacion), [
            '_token' => $token,
            'idEdicion' => $idEdicion,
        ]);
}

it('borra la posicion de tabla del grupo cuando se quita el equipo del grupo', function () {
    $admin = adminParaQuitarDelGrupo();
    $contexto = contextoQuitarDelGrupo('Edicion Quitar Grupo');
    $equipo = equipoEnGrupo($contexto, 'Mitai Quitado');

    $asignacion = EquipoGrupo::create([
        'idEquipo' => $equipo->id,
        'idGrupo' => $contexto['grupo']->id,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 3,
        'golesFavor' => 2,
        'golesContra' => 1,
        'diferenciaGoles' => 1,
        'jugado' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    quitarEquipoDelGrupo($this, $admin, $asignacion, $contexto['edicion']->id);

    $this->assertDatabaseMissing('equipos_grupos', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contexto['grupo']->id,
    ]);

    $this->assertDatabaseMissing('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $contexto['grupo']->id,
    ]);
});

it('no borra la posicion de tabla de otros grupos del mismo equipo', function () {
    $admin = adminParaQuitarDelGrupo();
    $contexto = contextoQuitarDelGrupo('Edicion Quitar Grupo Dos');
    $otroContexto = contextoQuitarDelGrupo('Edicion Quitar Grupo Otra');
    $equipo = equipoEnGrupo($contexto, 'Mitai En Dos Grupos');

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $otroContexto['edicion']->id,
        'idCategoria' => $otroContexto['categoria']->id,
    ]);

    $asignacion = EquipoGrupo::create([
        'idEquipo' => $equipo->id,
        'idGrupo' => $contexto['grupo']->id,
    ]);

    EquipoGrupo::create([
        'idEquipo' => $equipo->id,
        'idGrupo' => $otroContexto['grupo']->id,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 3,
        'golesFavor' => 2,
        'golesContra' => 1,
        'diferenciaGoles' => 1,
        'jugado' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    TablaPosicion::create([
        'idGrupo' => $otroContexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $otroContexto['edicion']->id,
        'puntos' => 6,
        'golesFavor' => 4,
        'golesContra' => 1,
        'diferenciaGoles' => 3,
        'jugado' => 2,
        'ganado' => 2,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    quitarEquipoDelGrupo($this, $admin, $asignacion, $contexto['edicion']->id);

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $otroContexto['grupo']->id,
    ]);

    $this->assertDatabaseHas('equipos_grupos', [
        'idEquipo' => $equipo->id,
        'idGrupo' => $otroContexto['grupo']->id,
    ]);
});
