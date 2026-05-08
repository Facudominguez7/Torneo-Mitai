<?php

declare(strict_types=1);

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\TablaGoleador;
use App\Services\EstadisticasJugadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('incrementa goles totales y crea tabla goleador con delta positivo', function (): void {
    $edicion = Edicion::create(['nombre' => 'Edicion Test']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => 'Equipo Test',
        'foto' => 'test.png',
    ]);

    $jugador = Jugador::create([
        'dni' => '40111222',
        'nombre' => 'Juan',
        'apellido' => 'Perez',
        'goles_totales' => 0,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    app(EstadisticasJugadorService::class)->actualizarDeltaGoles(
        $jugador->dni,
        $edicion->id,
        $categoria->id,
        $equipo->id,
        3,
    );

    expect((int) $jugador->fresh()->goles_totales)->toBe(3);

    $tabla = TablaGoleador::query()
        ->where('dni_jugador', $jugador->dni)
        ->where('idEdicion', $edicion->id)
        ->where('idCategoria', $categoria->id)
        ->first();

    expect($tabla)->not->toBeNull();
    expect((int) $tabla->cantidadGoles)->toBe(3);
});

it('decrementa goles y elimina tabla goleador cuando llega a cero', function (): void {
    $edicion = Edicion::create(['nombre' => 'Edicion Test']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $equipo = Equipo::create([
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
        'nombre' => 'Equipo Test',
        'foto' => 'test.png',
    ]);

    $jugador = Jugador::create([
        'dni' => '40111333',
        'nombre' => 'Pedro',
        'apellido' => 'Gomez',
        'goles_totales' => 2,
        'partidos_totales' => 0,
        'tarjetas_rojas_totales' => 0,
    ]);

    TablaGoleador::create([
        'dni_jugador' => $jugador->dni,
        'cantidadGoles' => 2,
        'idEquipo' => $equipo->id,
        'idEdicion' => $edicion->id,
        'idCategoria' => $categoria->id,
    ]);

    app(EstadisticasJugadorService::class)->actualizarDeltaGoles(
        $jugador->dni,
        $edicion->id,
        $categoria->id,
        $equipo->id,
        -2,
    );

    expect((int) $jugador->fresh()->goles_totales)->toBe(0);
    expect(TablaGoleador::query()->where('dni_jugador', $jugador->dni)->exists())->toBeFalse();
});
