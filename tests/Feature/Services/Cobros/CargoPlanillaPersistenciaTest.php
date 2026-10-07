<?php

use App\Models\CargoPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/FixturesCargos.php';

/**
 * Contratos del registro histórico CargoPlanilla: precio entero positivo,
 * morph hacia los dos tipos de padre, unicidad DB de la cuota por
 * equipo/partido, whitelist financiera de tipos, inmutabilidad Eloquent
 * y supervivencia del snapshot aunque desaparezcan el partido y los equipos.
 */
it('guarda el precio como entero positivo y expone relaciones con naming legacy', function () {
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, crearEquipoCargos($contexto, 'Mitai Rojo')));

    $cargo = CargoPlanilla::query()->make([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => ['equipo' => ['id' => $local->id, 'nombre' => $local->nombre]],
    ]);
    $cargo->parent()->associate($partido);
    $cargo->save();

    $cargo = $cargo->fresh();

    expect($cargo->precio_original)->toBeInt()->toBe(25000)
        ->and($cargo->tipo)->toBe('planilla_partido')
        ->and($cargo->parent)->toBeInstanceOf(Partido::class)
        ->and($cargo->parent->is($partido))->toBeTrue()
        ->and($cargo->equipo->is($local))->toBeTrue()
        ->and($cargo->edicion->is($contexto['edicion']))->toBeTrue()
        ->and($cargo->categoria->is($contexto['categoria']))->toBeTrue()
        ->and($cargo->snapshot['equipo']['nombre'])->toBe('Mitai Azul')
        ->and(DB::table('cargo_planillas')->count())->toBe(1);
});

it('acepta los dos tipos de padre con ids fuente 1 sin colisionar', function () {
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $visitante = crearEquipoCargos($contexto, 'Mitai Rojo');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante));
    $final = InstanciaFinal::create(crearDatosFinalCargos($contexto, $local, $visitante));

    // Ambas fuentes con id 1: el tipo financiero evita la colisión de la cuota.
    $cargoPartido = CargoPlanilla::query()->make([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [],
    ]);
    $cargoPartido->parent()->associate($partido);
    $cargoPartido->save();

    $cargoFinal = CargoPlanilla::query()->make([
        'tipo' => CargoPlanilla::TIPO_INSTANCIA_FINAL,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [],
    ]);
    $cargoFinal->parent()->associate($final);
    $cargoFinal->save();

    expect(CargoPlanilla::query()->count())->toBe(2)
        ->and($cargoPartido->fresh()->parent)->toBeInstanceOf(Partido::class)
        ->and($cargoFinal->fresh()->parent)->toBeInstanceOf(InstanciaFinal::class)
        ->and($cargoPartido->fresh()->parent_type)->toBe(Partido::class)
        ->and($cargoFinal->fresh()->parent_type)->toBe(InstanciaFinal::class);
});

it('rechaza duplicar la cuota del mismo equipo en el mismo padre con la unique de DB', function () {
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $visitante = crearEquipoCargos($contexto, 'Mitai Rojo');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante));

    $base = [
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [],
    ];
    $cargoLocal = CargoPlanilla::query()->make($base);
    $cargoLocal->parent()->associate($partido);
    $cargoLocal->save();

    // Mismo tipo + mismo padre + mismo equipo: única cuota por equipo/partido.
    expect(function () use ($base, $partido) {
        $duplicado = CargoPlanilla::query()->make($base);
        $duplicado->parent()->associate($partido);
        $duplicado->save();
    })->toThrow(QueryException::class)
        ->and(CargoPlanilla::query()->count())->toBe(1);

    // El equipo visitante sí genera su propia cuota en el mismo padre.
    $cargoVisitante = CargoPlanilla::query()->make(array_merge($base, ['idEquipo' => $visitante->id]));
    $cargoVisitante->parent()->associate($partido);
    $cargoVisitante->save();

    expect(CargoPlanilla::query()->count())->toBe(2);
});
