<?php

use App\Models\CargoPlanilla;
use App\Models\Partido;

require_once __DIR__.'/FixturesCargos.php';

it('valida el precio original antes de cualquier cast monetario', function (mixed $precio) {
    expect(fn () => CargoPlanilla::query()->create([
        'tipo' => 'planilla_partido', 'parent_type' => Partido::class, 'parent_id' => 1,
        'idEquipo' => 1, 'idEdicion' => 1, 'idCategoria' => 1, 'precio_original' => $precio, 'snapshot' => [],
    ]))->toThrow(RuntimeException::class, 'El precio del cargo')->and(CargoPlanilla::query()->count())->toBe(0);
})->with([[25000.75], [25000.0], ['25000'], ['25000.75'], [true], [2147483648]]);
