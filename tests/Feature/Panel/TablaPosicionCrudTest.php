<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TablaPosicion;
use App\Models\User;

function crearAdminTablaPosiciones(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoTablaPosicionesCrud(string $nombreEdicion, string $nombreCategoria, string $nombreGrupo): array
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

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Azul',
        'foto' => 'escudo.png',
    ]);

    return compact('edicion', 'categoria', 'grupo', 'equipo');
}

it('muestra el enlace a tabla de posiciones en la barra lateral del panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Sidebar', 'Sub 16', 'Grupo A');

    $response = $this->actingAs($admin)->get(route('admin', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]), false)
        ->assertSee('Tabla de Posiciones');
});

it('muestra las posiciones de la edicion seleccionada en el panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Listado', 'Sub 17', 'Grupo B');

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 12,
        'golesFavor' => 10,
        'golesContra' => 4,
        'diferenciaGoles' => 6,
        'jugado' => 4,
        'ganado' => 4,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Tabla de Posiciones')
        ->assertSee('Mitai Azul')
        ->assertSee('12')
        ->assertSee('Agregar Posición');
});

it('muestra el estado vacio cuando la edicion no tiene posiciones', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Vacia', 'Sub 18', 'Grupo C');

    $response = $this->actingAs($admin)->get(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('No existen registros');
});

it('crea una posicion y vuelve al contexto de la edicion', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Alta', 'Sub 19', 'Grupo D');

    $response = $this->actingAs($admin)->post(route('tabla_posicion.store'), [
        'idEdicion' => $contexto['edicion']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'puntos' => 15,
        'golesFavor' => 14,
        'golesContra' => 5,
        'diferenciaGoles' => 9,
        'jugado' => 5,
        'ganado' => 5,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    $response->assertRedirect(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $this->assertDatabaseHas('tabla_posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'puntos' => 15,
    ]);
});

it('actualiza una posicion sin perder la edicion activa', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Update', 'Sub 20', 'Grupo E');
    $tablaPosicion = TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 8,
        'golesFavor' => 7,
        'golesContra' => 6,
        'diferenciaGoles' => 1,
        'jugado' => 4,
        'ganado' => 2,
        'perdido' => 1,
        'empatado' => 1,
    ]);

    $response = $this->actingAs($admin)->put(route('tabla_posicion.update', $tablaPosicion), [
        'idEdicion' => $contexto['edicion']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'puntos' => 18,
        'golesFavor' => 16,
        'golesContra' => 5,
        'diferenciaGoles' => 11,
        'jugado' => 6,
        'ganado' => 6,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    $response->assertRedirect(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $this->assertDatabaseHas('tabla_posiciones', [
        'id' => $tablaPosicion->id,
        'puntos' => 18,
        'golesFavor' => 16,
        'golesContra' => 5,
        'diferenciaGoles' => 11,
    ]);
});

it('elimina una posicion desde el panel', function () {
    $admin = crearAdminTablaPosiciones();
    $contexto = crearContextoTablaPosicionesCrud('Edicion Delete', 'Sub 21', 'Grupo F');
    $tablaPosicion = TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'puntos' => 3,
        'golesFavor' => 2,
        'golesContra' => 4,
        'diferenciaGoles' => -2,
        'jugado' => 3,
        'ganado' => 1,
        'perdido' => 2,
        'empatado' => 0,
    ]);

    $response = $this->actingAs($admin)->delete(route('tabla_posicion.destroy', $tablaPosicion), [
        'idEdicion' => $contexto['edicion']->id,
    ]);

    $response->assertRedirect(route('tabla_posicion.index', ['idEdicion' => $contexto['edicion']->id]));

    $this->assertDatabaseMissing('tabla_posiciones', [
        'id' => $tablaPosicion->id,
    ]);
});
