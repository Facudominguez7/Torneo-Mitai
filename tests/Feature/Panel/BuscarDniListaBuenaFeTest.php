<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\User;

function crearContextoListaBuenaFeParaBusqueda(): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => 'Edicion Busqueda DNI',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Verde',
        'foto' => 'equipo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return compact('admin', 'edicion', 'categoria', 'equipo');
}

function crearJugadorListaBuenaFe(array $contexto, string $dni, string $nombre, string $apellido, bool $activo = true): void
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2014-05-10',
        'numero_camiseta' => 7,
        'fecha_alta' => now()->subDays(3),
        'activo' => $activo,
        'fecha_baja' => $activo ? null : now()->subDay(),
        'motivo_baja' => $activo ? null : 'Baja de prueba',
    ]);
}

it('filtra jugadores activos e inactivos por dni en la vista de lista buena fe', function () {
    $contexto = crearContextoListaBuenaFeParaBusqueda();

    crearJugadorListaBuenaFe($contexto, '40111222', 'Mateo', 'Perez', true);
    crearJugadorListaBuenaFe($contexto, '40999888', 'Juan', 'Gomez', false);
    crearJugadorListaBuenaFe($contexto, '45555666', 'Lucia', 'Lopez', true);

    $this->actingAs($contexto['admin'])
        ->get(route('lista-buena-fe.show', [
            'idEquipo' => $contexto['equipo']->id,
            'idEdicion' => $contexto['edicion']->id,
            'buscar_dni' => '4011',
        ]))
        ->assertOk()
        ->assertSee('40111222')
        ->assertDontSee('40999888')
        ->assertDontSee('45555666');
});

it('muestra mensaje cuando no encuentra coincidencias por dni', function () {
    $contexto = crearContextoListaBuenaFeParaBusqueda();

    crearJugadorListaBuenaFe($contexto, '40111222', 'Mateo', 'Perez', true);

    $this->actingAs($contexto['admin'])
        ->get(route('lista-buena-fe.show', [
            'idEquipo' => $contexto['equipo']->id,
            'idEdicion' => $contexto['edicion']->id,
            'buscar_dni' => '9999',
        ]))
        ->assertOk()
        ->assertSee('No se encontraron jugadores activos ni inactivos para el DNI')
        ->assertSee('9999');
});
