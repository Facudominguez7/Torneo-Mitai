<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\Equipo;
use App\Models\ExencionCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/FixturesCargos.php';

function crearCargoAplicaciones(array $contexto, Equipo $equipo): CargoPlanilla
{
    $rival = crearEquipoCargos($contexto, 'Rival Aplicaciones');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Aplicaciones');
    $this->cargo = crearCargoAplicaciones($this->contexto, $this->equipo);
    $this->cuenta = CuentaCobroPlanilla::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id]);
    $this->admin = crearAdminCargos();
    $this->pago = PagoPlanilla::create([
        'cuenta_id' => $this->cuenta->id, 'cargo_origen_id' => $this->cargo->id, 'usuario_id' => $this->admin->id,
        'importe_ars' => 25000, 'medio' => 'efectivo', 'clave_idempotencia' => (string) Str::uuid(),
    ]);
    $this->datos = ['pago_id' => $this->pago->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 5000];
});

it('limita nuevas porciones a deuda efectiva agregada con exencion y pagos previos', function (int $eximido, int $importe, bool $aceptado): void {
    AplicacionPagoPlanilla::create($this->datos);
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => $eximido]);
    $crear = fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe]));
    if ($aceptado) {
        expect($crear()->importe_ars)->toBe($importe);
    } else {
        expect($crear)->toThrow(LogicException::class);
    }
    $this->assertDatabaseCount('aplicacion_pago_planillas', $aceptado ? 2 : 1);
    expect($this->pago->fresh()->importe_ars)->toBe(25000)->and($this->cargo->fresh()->precio_original)->toBe(25000);
})->with([
    'total pendiente eximido' => [20000, 1, false],
    'exceso residual' => [12000, 8001, false],
    'residual exacto' => [12000, 8000, true],
    'suma corrupta' => [25000, 1, false],
]);

it('no amplia la guarda a disponibilidad agregada del pago', function (): void {
    $otroCargo = crearCargoAplicaciones($this->contexto, $this->equipo);
    AplicacionPagoPlanilla::create(array_replace($this->datos, ['importe_ars' => 25000]));
    expect(AplicacionPagoPlanilla::create(array_replace($this->datos, ['cargo_id' => $otroCargo->id]))->importe_ars)->toBe(5000);
});

it('conserva una porcion interna y sus relaciones sin generar otro ingreso', function (): void {
    $aplicacion = AplicacionPagoPlanilla::create($this->datos)->fresh();

    expect($aplicacion->importe_ars)->toBeInt()->toBe(5000)
        ->and($aplicacion->pago->is($this->pago))->toBeTrue()
        ->and($aplicacion->cargo->is($this->cargo))->toBeTrue()
        ->and($this->pago->fresh()->importe_ars)->toBe(25000)
        ->and($this->cargo->fresh()->precio_original)->toBe(25000);
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
    $this->assertDatabaseHas('configuracion_cobro_planillas', ['id' => 1, 'activado_at' => null]);
});

it('puede distribuir el mismo ingreso entre cargos del mismo equipo y edicion', function (): void {
    AplicacionPagoPlanilla::create($this->datos);
    $otroCargo = crearCargoAplicaciones($this->contexto, $this->equipo);
    AplicacionPagoPlanilla::create(array_replace($this->datos, ['cargo_id' => $otroCargo->id, 'importe_ars' => 20000]));

    expect((int) AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->sum('importe_ars'))->toBe(25000)
        ->and((int) PagoPlanilla::query()->sum('importe_ars'))->toBe(25000);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 2);
    $this->assertDatabaseCount('pago_planillas', 1);
});

it('acepta porciones de varios pagos y varios parciales hacia el mismo cargo', function (): void {
    AplicacionPagoPlanilla::create($this->datos);
    AplicacionPagoPlanilla::create($this->datos);
    $otroPago = PagoPlanilla::create(array_replace($this->pago->only(['cuenta_id', 'cargo_origen_id', 'usuario_id', 'importe_ars', 'medio']), [
        'importe_ars' => 10000, 'clave_idempotencia' => (string) Str::uuid(),
    ]));
    AplicacionPagoPlanilla::create(array_replace($this->datos, ['pago_id' => $otroPago->id]));

    expect((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(15000);
    $this->assertDatabaseCount('pago_planillas', 2);
});

it('fija fecha del servidor aunque se suministre una fecha historica', function (): void {
    $this->travelTo(Carbon::parse('2026-10-08 11:10:00'));
    $aplicacion = new AplicacionPagoPlanilla($this->datos);
    $aplicacion->forceFill(['created_at' => '2000-01-01 00:00:00'])->save();

    expect($aplicacion->fresh()->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($aplicacion->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-08 11:10:00');
});

it('rechaza importes ambiguos fuera de rango o superiores al limite por fila', function (mixed $importe): void {
    expect(fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
})->with([0, -1, true, null, '5000', 1.5, 25001, 2147483648]);

it('rechaza una porcion que excede el ingreso aun si el cargo admite mas', function (): void {
    $pago = PagoPlanilla::create(array_replace($this->pago->only(['cuenta_id', 'cargo_origen_id', 'usuario_id', 'medio']), [
        'importe_ars' => 1000, 'clave_idempotencia' => (string) Str::uuid(),
    ]));

    expect(fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, ['pago_id' => $pago->id])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
});

it('acepta los extremos dentro del limite por fila', function (int $importe): void {
    expect(AplicacionPagoPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe]))->fresh()->importe_ars)->toBe($importe);
})->with([1, 25000]);

it('rechaza identificadores internos ambiguos', function (string $campo, mixed $valor): void {
    expect(fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, [$campo => $valor])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
})->with(['pago_id', 'cargo_id'])->with([true, null, '1', 0, -1]);

it('rechaza pago o cargo inexistentes', function (string $campo): void {
    expect(fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, [$campo => 2147483647])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
})->with(['pago_id', 'cargo_id']);

it('no permite cruces de equipo ni de edicion con el mismo equipo', function (bool $otraEdicion): void {
    $contexto = $otraEdicion ? crearContextoCargos('Otra Edicion Aplicaciones') : $this->contexto;
    $equipo = $otraEdicion ? $this->equipo : crearEquipoCargos($contexto, 'Otro Equipo Aplicaciones');
    $cargo = crearCargoAplicaciones($contexto, $equipo);

    expect(fn () => AplicacionPagoPlanilla::create(array_replace($this->datos, ['cargo_id' => $cargo->id])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
})->with([true, false]);

it('no permite editar ni borrar aplicaciones por instancia', function (bool $borrar): void {
    $aplicacion = AplicacionPagoPlanilla::create($this->datos);
    expect(fn () => $borrar ? $aplicacion->delete() : $aplicacion->update(['importe_ars' => 1]))->toThrow(LogicException::class);
    $this->assertDatabaseHas('aplicacion_pago_planillas', ['id' => $aplicacion->id, 'importe_ars' => 5000]);
})->with([true, false]);

it('conserva enlaces financieros usados frente a borrados SQL directos', function (string $tabla): void {
    $cargoDestino = crearCargoAplicaciones($this->contexto, $this->equipo);
    AplicacionPagoPlanilla::create(array_replace($this->datos, ['cargo_id' => $cargoDestino->id]));
    $id = $tabla === 'pago_planillas' ? $this->pago->id : $cargoDestino->id;
    expect(fn () => DB::table($tabla)->where('id', $id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 1);
})->with(['pago_planillas', 'cargo_planillas']);

it('rechaza referencias huerfanas en inserciones SQL directas', function (string $campo): void {
    $datos = array_replace($this->datos, [$campo => 2147483647, 'created_at' => now()->format('Y-m-d H:i:s')]);

    expect(fn () => DB::table('aplicacion_pago_planillas')->insert($datos))->toThrow(QueryException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
})->with(['pago_id', 'cargo_id']);

it('conserva trazabilidad del ingreso cuando se elimina el operador original', function (): void {
    AplicacionPagoPlanilla::create($this->datos);
    User::query()->whereKey($this->admin->id)->delete();
    $aplicacion = AplicacionPagoPlanilla::query()->firstOrFail();

    expect($aplicacion->pago->usuario_id)->toBeNull()
        ->and($aplicacion->pago->actor_nombre)->toBe('Admin Cobros')
        ->and($aplicacion->importe_ars)->toBe(5000);
});
