<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;

function contextoPosicionesEliminados(string $nombreEdicion = 'Edicion Eliminados Posiciones'): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create(['nombre' => $nombreEdicion]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 16',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Unico',
    ]);

    return compact('admin', 'edicion', 'categoria', 'grupo');
}

function filaPosicionEliminados(
    array $contexto,
    string $nombreEquipo,
    bool $inscripto,
    int $idEdicionFila,
    int $puntos = 0,
): TablaPosicion {
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombreEquipo,
        'foto' => 'escudo.png',
    ]);

    if ($inscripto) {
        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
        ]);
    }

    return TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $idEdicionFila,
        'puntos' => $puntos,
        'golesFavor' => 0,
        'golesContra' => 0,
        'diferenciaGoles' => 0,
        'jugado' => 0,
        'ganado' => 0,
        'perdido' => 0,
        'empatado' => 0,
    ]);
}

it('la tabla publica no muestra equipos que ya no estan inscriptos en la edicion', function (): void {
    $contexto = contextoPosicionesEliminados('Edicion Publica Eliminados');

    filaPosicionEliminados($contexto, 'Publico Sigue Inscripto', inscripto: true, idEdicionFila: $contexto['edicion']->id, puntos: 6);
    filaPosicionEliminados($contexto, 'Publico Borrado De La Edicion', inscripto: false, idEdicionFila: $contexto['edicion']->id, puntos: 9);

    $this->get(route('tabla-posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]))
        ->assertOk()
        ->assertSee('Publico Sigue Inscripto', false)
        ->assertDontSee('Publico Borrado De La Edicion', false);
});

it('la tabla publica mantiene las filas legacy con idEdicion en cero de equipos inscriptos', function (): void {
    $contexto = contextoPosicionesEliminados('Edicion Publica Legacy');

    filaPosicionEliminados($contexto, 'Publico Legacy Inscripto', inscripto: true, idEdicionFila: 0);

    $this->get(route('tabla-posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]))
        ->assertOk()
        ->assertSee('Publico Legacy Inscripto', false);
});

it('el panel de tabla de posiciones no lista equipos que ya no estan inscriptos', function (): void {
    $contexto = contextoPosicionesEliminados('Edicion Panel Eliminados');

    filaPosicionEliminados($contexto, 'Panel Sigue Inscripto', inscripto: true, idEdicionFila: $contexto['edicion']->id);
    filaPosicionEliminados($contexto, 'Panel Borrado De La Edicion', inscripto: false, idEdicionFila: $contexto['edicion']->id);

    $this->actingAs($contexto['admin'])
        ->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Panel Sigue Inscripto', false)
        ->assertDontSee('Panel Borrado De La Edicion', false);
});
