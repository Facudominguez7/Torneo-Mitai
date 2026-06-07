<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminInstanciaFinal(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearEquipoInstanciaFinal(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return $equipo;
}

it('muestra chips de asistencia pendiente para instancias finales', function () {
    $admin = crearAdminInstanciaFinal();
    $edicion = Edicion::create(['nombre' => 'Edicion Finales Test']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 10',
    ]);
    $fase = Fase::create(['nombre' => 'Final']);
    $local = crearEquipoInstanciaFinal($edicion, $categoria, 'Mitai Local');
    $visitante = crearEquipoInstanciaFinal($edicion, $categoria, 'Mitai Visitante');

    $partido = InstanciaFinal::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => '2026-03-20 10:00:00',
        'cancha' => '1',
    ]);

    Jugador::create([
        'dni' => 40100111,
        'nombre' => 'Jugador',
        'apellido' => 'Local',
    ]);

    PlanillaJugador::create([
        'partido_id' => $partido->id,
        'partido_type' => InstanciaFinal::class,
        'dni_jugador' => 40100111,
        'idEquipo' => $local->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'numero_camiseta' => 10,
        'goles' => 0,
        'asistio' => false,
        'tarjeta_roja' => false,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('instancia_final.index', ['idEdicion' => $edicion->id]));

    $response->assertOk()
        ->assertViewHas('equiposSinAsistencia')
        ->assertSee('Mitai Local (Local): Sin asistencias marcadas')
        ->assertSee('Mitai Visitante (Visitante): Sin planilla');
});
