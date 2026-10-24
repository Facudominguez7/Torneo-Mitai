<?php

// La migración corrige los nombres ya cargados con la misma regla que usa el modelo al guardar.

use Illuminate\Support\Facades\DB;

function migracionNombresMayuscula(): object
{
    return require database_path('migrations/2026_10_24_200000_capitalize_jugadores_nombres.php');
}

function insertarJugadorCrudo(string $dni, string $nombre, string $apellido): void
{
    DB::table('jugadores')->insert([
        'dni' => $dni, 'nombre' => $nombre, 'apellido' => $apellido,
        'goles_totales' => 4, 'partidos_totales' => 7, 'tarjetas_rojas_totales' => 1,
    ]);
}

it('pone la inicial de cada palabra en mayuscula en los jugadores existentes', function (): void {
    insertarJugadorCrudo('40000001', 'juan pablo', 'gómez díaz');
    insertarJugadorCrudo('40000002', 'MATEO', 'DE LA FUENTE');
    insertarJugadorCrudo('40000003', ' lucía ', 'pérez ');

    migracionNombresMayuscula()->up();

    expect(DB::table('jugadores')->orderBy('dni')->get(['dni', 'nombre', 'apellido', 'goles_totales', 'partidos_totales', 'tarjetas_rojas_totales'])->map(fn ($fila): array => (array) $fila)->all())->toBe([
        ['dni' => 40000001, 'nombre' => 'Juan Pablo', 'apellido' => 'Gómez Díaz', 'goles_totales' => 4, 'partidos_totales' => 7, 'tarjetas_rojas_totales' => 1],
        ['dni' => 40000002, 'nombre' => 'Mateo', 'apellido' => 'De La Fuente', 'goles_totales' => 4, 'partidos_totales' => 7, 'tarjetas_rojas_totales' => 1],
        ['dni' => 40000003, 'nombre' => 'Lucía', 'apellido' => 'Pérez', 'goles_totales' => 4, 'partidos_totales' => 7, 'tarjetas_rojas_totales' => 1],
    ]);
});

it('no actualiza los jugadores que ya estan bien escritos y se puede correr de nuevo', function (): void {
    insertarJugadorCrudo('40000001', 'Juan Pablo', 'Gómez');
    insertarJugadorCrudo('40000002', 'mateo', 'perez');
    $actualizaciones = 0;
    DB::listen(function ($consulta) use (&$actualizaciones): void {
        if (str_starts_with(strtolower($consulta->sql), 'update')) {
            $actualizaciones++;
        }
    });

    migracionNombresMayuscula()->up();
    migracionNombresMayuscula()->up();

    expect($actualizaciones)->toBe(1)
        ->and(DB::table('jugadores')->pluck('nombre', 'dni')->all())->toBe(['40000001' => 'Juan Pablo', '40000002' => 'Mateo']);
});
