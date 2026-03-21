<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

function crearAdminEstadoGoles(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoEstadoGoles(): array
{
    $edicion = Edicion::create([
        'nombre' => 'Edicion Estado Goles',
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 14',
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Estado',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Estado',
    ]);

    return compact('edicion', 'categoria', 'fecha', 'grupo');
}

function crearEquipoEstadoGoles(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $contexto['categoria']->id,
    ]);

    return $equipo;
}

function crearPartidoEstadoGoles(array $contexto, Equipo $local, Equipo $visitante): Partido
{
    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-03-15 13:00:00',
    ]);
}

it('muestra goles pendientes de carga en el card admin cuando el resultado ya existe sin confirmacion', function () {
    $admin = crearAdminEstadoGoles();
    $contexto = crearContextoEstadoGoles();
    $local = crearEquipoEstadoGoles($contexto, 'Mitai Naranja');
    $visitante = crearEquipoEstadoGoles($contexto, 'Mitai Gris');
    $partido = crearPartidoEstadoGoles($contexto, $local, $visitante);

    $partido->update([
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'goles_confirmados_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Goles pendientes de carga', false);
});

it('muestra goles confirmados en el card admin cuando la revision ya fue confirmada', function () {
    $admin = crearAdminEstadoGoles();
    $contexto = crearContextoEstadoGoles();
    $local = crearEquipoEstadoGoles($contexto, 'Mitai Coral');
    $visitante = crearEquipoEstadoGoles($contexto, 'Mitai Arena');
    $partido = crearPartidoEstadoGoles($contexto, $local, $visitante);

    $partido->update([
        'goles_confirmados_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Goles confirmados', false);
});

it('no inventa estado de goles en el card admin cuando falta cargar resultado oficial', function () {
    $admin = crearAdminEstadoGoles();
    $contexto = crearContextoEstadoGoles();
    $local = crearEquipoEstadoGoles($contexto, 'Mitai Cielo');
    $visitante = crearEquipoEstadoGoles($contexto, 'Mitai Tierra');
    $partido = crearPartidoEstadoGoles($contexto, $local, $visitante);

    $partido->update([
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'goles_confirmados_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Goles pendientes de carga', false)
        ->assertDontSee('Goles confirmados', false);
});
