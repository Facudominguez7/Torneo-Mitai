<?php

use App\Models\CuentaCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Mitai Cuenta');
    $this->identidad = ['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id];
});

it('persiste una cuenta interna sin columnas de saldo ni efectos financieros', function (): void {
    $cuenta = CuentaCobroPlanilla::create($this->identidad)->fresh();

    expect($cuenta->idEquipo)->toBeInt()->toBe($this->equipo->id)
        ->and($cuenta->idEdicion)->toBeInt()->toBe($this->contexto['edicion']->id)
        ->and($cuenta->equipo->is($this->equipo))->toBeTrue()
        ->and($cuenta->edicion->is($this->contexto['edicion']))->toBeTrue()
        ->and(Schema::getColumnListing('cuenta_cobro_planillas'))->toEqualCanonicalizing([
            'id', 'idEquipo', 'idEdicion', 'created_at', 'updated_at',
        ]);

    $this->assertDatabaseCount('cargo_planillas', 0);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
    $this->assertDatabaseHas('configuracion_cobro_planillas', ['id' => 1, 'activado_at' => null]);
});

it('reutiliza la misma identidad al solicitar nuevamente la cuenta', function (): void {
    $original = CuentaCobroPlanilla::firstOrCreate($this->identidad);
    $reutilizada = CuentaCobroPlanilla::firstOrCreate($this->identidad);

    expect($reutilizada->getKey())->toBe($original->getKey())
        ->and($reutilizada->wasRecentlyCreated)->toBeFalse();
    $this->assertDatabaseCount('cuenta_cobro_planillas', 1);
});

it('impide duplicar la pareja equipo edicion incluso mediante SQL directo', function (): void {
    CuentaCobroPlanilla::create($this->identidad);

    expect(fn () => DB::table('cuenta_cobro_planillas')->insert($this->identidad))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('cuenta_cobro_planillas', 1);
});

it('separa equipos y ediciones aunque compartan parte de la identidad', function (): void {
    $otroEquipo = crearEquipoCargos($this->contexto, 'Mitai Otra Cuenta');
    $otraEdicion = crearContextoCargos('Otra Edicion Cuenta');
    EquipoEdicion::create([
        'idEquipo' => $this->equipo->id,
        'idEdicion' => $otraEdicion['edicion']->id,
        'idCategoria' => $otraEdicion['categoria']->id,
    ]);

    $primera = CuentaCobroPlanilla::create($this->identidad);
    $segunda = CuentaCobroPlanilla::create(array_replace($this->identidad, ['idEquipo' => $otroEquipo->id]));
    $tercera = CuentaCobroPlanilla::create(array_replace($this->identidad, ['idEdicion' => $otraEdicion['edicion']->id]));

    expect([$primera->id, $segunda->id, $tercera->id])->toHaveCount(3)
        ->and(array_unique([$primera->id, $segunda->id, $tercera->id]))->toHaveCount(3);
    $this->assertDatabaseCount('cuenta_cobro_planillas', 3);
});

it('rechaza identidades internas invalidas antes del cast sin crear cuentas', function (string $campo, mixed $valor): void {
    expect(fn () => CuentaCobroPlanilla::create(array_replace($this->identidad, [$campo => $valor])))
        ->toThrow(LogicException::class);
    $this->assertDatabaseCount('cuenta_cobro_planillas', 0);
})->with(['equipo' => ['idEquipo'], 'edicion' => ['idEdicion']])->with([
    'nulo' => [null], 'cero' => [0], 'negativo' => [-1], 'decimal' => [1.5],
    'booleano' => [true], 'cadena numerica' => ['1'], 'fuera de rango' => [2147483648],
]);

it('rechaza entidades inexistentes y equipos no inscriptos en la edicion', function (string $caso): void {
    $identidad = $this->identidad;
    if ($caso === 'equipo ausente') {
        $identidad['idEquipo'] = 2147483647;
    } elseif ($caso === 'edicion ausente') {
        $identidad['idEdicion'] = 2147483647;
    } else {
        $identidad['idEdicion'] = crearContextoCargos('Sin Inscripcion Cuenta')['edicion']->id;
    }

    expect(fn () => CuentaCobroPlanilla::create($identidad))->toThrow(LogicException::class);
    $this->assertDatabaseCount('cuenta_cobro_planillas', 0);
})->with(['equipo ausente', 'edicion ausente', 'sin inscripcion']);

it('acepta una inscripcion duplicada sin crear cuentas adicionales', function (): void {
    EquipoEdicion::create(array_merge($this->identidad, ['idCategoria' => $this->contexto['categoria']->id]));

    CuentaCobroPlanilla::firstOrCreate($this->identidad);
    CuentaCobroPlanilla::firstOrCreate($this->identidad);

    $this->assertDatabaseCount('cuenta_cobro_planillas', 1);
});

it('bloquea reasignaciones y borrado por instancia conservando la identidad original', function (string $operacion): void {
    $cuenta = CuentaCobroPlanilla::create($this->identidad);
    $idOriginal = $cuenta->id;
    $otraCuenta = CuentaCobroPlanilla::create(array_replace($this->identidad, [
        'idEquipo' => crearEquipoCargos($this->contexto, 'Cuenta Protegida')->id,
    ]));

    $accion = match ($operacion) {
        'equipo' => fn () => $cuenta->update(['idEquipo' => $otraCuenta->idEquipo]),
        'edicion' => fn () => $cuenta->update(['idEdicion' => 2147483647]),
        'pk' => fn () => $cuenta->forceFill(['id' => $otraCuenta->id])->save(),
        'borrar' => fn () => $cuenta->delete(),
        'borrar pk alterada' => fn () => $cuenta->forceFill(['id' => $otraCuenta->id])->delete(),
    };

    expect($accion)->toThrow(LogicException::class);
    $this->assertDatabaseHas('cuenta_cobro_planillas', array_merge(['id' => $idOriginal], $this->identidad));
    $this->assertDatabaseHas('cuenta_cobro_planillas', ['id' => $otraCuenta->id, 'idEquipo' => $otraCuenta->idEquipo]);
    $this->assertDatabaseCount('cuenta_cobro_planillas', 2);
})->with(['equipo', 'edicion', 'pk', 'borrar', 'borrar pk alterada']);

it('conserva la cuenta cuando desaparecen sus referencias deportivas', function (): void {
    $cuenta = CuentaCobroPlanilla::create($this->identidad);
    EquipoEdicion::query()->where($this->identidad)->delete();
    Equipo::query()->whereKey($this->equipo->id)->delete();
    Edicion::query()->whereKey($this->contexto['edicion']->id)->delete();

    $historica = $cuenta->fresh();
    expect($historica)->not->toBeNull()
        ->and($historica->idEquipo)->toBe($this->identidad['idEquipo'])
        ->and($historica->idEdicion)->toBe($this->identidad['idEdicion'])
        ->and($historica->equipo)->toBeNull()
        ->and($historica->edicion)->toBeNull();
});
