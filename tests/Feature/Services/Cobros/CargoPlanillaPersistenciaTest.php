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

it('rechaza tipos fuera de la whitelist financiera y tipos inconsistentes con el padre', function (string $tipo, ?string $parentType) {
    $contexto = crearContextoCargos();

    expect(fn () => CargoPlanilla::query()->create([
        'tipo' => $tipo,
        'parent_type' => $parentType,
        'parent_id' => 1,
        'idEquipo' => 1,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [],
    ]))->toThrow(RuntimeException::class)
        ->and(CargoPlanilla::query()->count())->toBe(0);

    // Literales en el dataset: el archivo debe cargar antes de que exista la clase bajo test.
})->with([
    'tipo inventado' => ['cuota_inventada', Partido::class],
    'tipo case-sensitive' => ['Planilla_Partido', Partido::class],
    'tipo vacío' => ['', Partido::class],
    'tipo de partido con padre final' => ['planilla_partido', InstanciaFinal::class],
    'tipo de final con padre partido' => ['planilla_instancia_final', Partido::class],
]);

it('rechaza precios que no son enteros positivos', function (int $precio) {
    $contexto = crearContextoCargos();

    expect(fn () => CargoPlanilla::query()->create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'parent_type' => Partido::class,
        'parent_id' => 1,
        'idEquipo' => 1,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => $precio,
        'snapshot' => [],
    ]))->toThrow(RuntimeException::class)
        ->and(CargoPlanilla::query()->count())->toBe(0);
})->with([
    'cero' => [0],
    'negativo' => [-25000],
]);

it('impide editar el precio o la identidad del cargo mediante instancias Eloquent', function (array $cambios) {
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, crearEquipoCargos($contexto, 'Mitai Rojo')));
    $cargo = CargoPlanilla::query()->make([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => ['equipo' => ['nombre' => 'Mitai Azul']],
    ]);
    $cargo->parent()->associate($partido);
    $cargo->save();

    expect(fn () => $cargo->update($cambios))->toThrow(LogicException::class)
        ->and($cargo->fresh()->precio_original)->toBe(25000)
        ->and($cargo->fresh()->tipo)->toBe(CargoPlanilla::TIPO_PARTIDO)
        ->and($cargo->fresh()->idEquipo)->toBe($local->id)
        ->and($cargo->fresh()->snapshot['equipo']['nombre'])->toBe('Mitai Azul');
})->with([
    'precio' => [['precio_original' => 1]],
    'tipo' => [['tipo' => 'planilla_instancia_final']],
    'equipo' => [['idEquipo' => 999]],
    'padre' => [['parent_id' => 999]],
    'snapshot' => [['snapshot' => ['equipo' => ['nombre' => 'Editado']]]],
]);

it('impide borrar el cargo mediante instancias Eloquent', function () {
    $contexto = crearContextoCargos();
    $cargo = CargoPlanilla::query()->create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'parent_type' => Partido::class,
        'parent_id' => 1,
        'idEquipo' => 1,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [],
    ]);

    expect(fn () => $cargo->delete())->toThrow(LogicException::class)
        ->and(CargoPlanilla::query()->count())->toBe(1);
});

it('conserva el snapshot de historia aunque se borren el partido y los equipos', function () {
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $visitante = crearEquipoCargos($contexto, 'Mitai Rojo');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante));

    $cargo = CargoPlanilla::query()->make([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => 25000,
        'snapshot' => [
            'equipo' => ['id' => $local->id, 'nombre' => 'Mitai Azul'],
            'oponente' => ['id' => $visitante->id, 'nombre' => 'Mitai Rojo'],
            'edicion' => ['id' => $contexto['edicion']->id, 'nombre' => 'Edicion Cobros'],
            'categoria' => ['id' => $contexto['categoria']->id, 'nombre' => 'Sub 12 Cobros'],
            'cancha' => 1,
            'fecha_partido' => '2026-04-05 10:00:00',
            'partido' => [
                'id' => $partido->id,
                'public_token' => $partido->public_token,
                'es_interzonal' => false,
                'id_fecha' => $partido->idFechas,
            ],
        ],
    ]);
    $cargo->parent()->associate($partido);
    $cargo->save();

    $partido->delete();
    $local->delete();
    $visitante->delete();

    $cargo = $cargo->fresh();

    expect($cargo)->not->toBeNull()
        ->and($cargo->parent)->toBeNull()
        ->and($cargo->equipo)->toBeNull()
        ->and($cargo->edicion->is($contexto['edicion']))->toBeTrue()
        ->and($cargo->categoria->is($contexto['categoria']))->toBeTrue()
        ->and($cargo->snapshot['equipo'])->toBe(['id' => $local->id, 'nombre' => 'Mitai Azul'])
        ->and($cargo->snapshot['oponente'])->toBe(['id' => $visitante->id, 'nombre' => 'Mitai Rojo'])
        ->and($cargo->snapshot['partido']['public_token'])->toBe($partido->public_token)
        ->and($cargo->snapshot['fecha_partido'])->toBe('2026-04-05 10:00:00');
});
