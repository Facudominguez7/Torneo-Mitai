<?php

use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\EquipoEdicion;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Pago');
    $this->admin = crearAdminCargos('Operador Original');
    $this->cuenta = CuentaCobroPlanilla::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id]);
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, crearEquipoCargos($this->contexto, 'Rival Pago')));
    $this->cargo = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    $this->datos = [
        'cuenta_id' => $this->cuenta->id, 'cargo_origen_id' => $this->cargo->id, 'usuario_id' => $this->admin->id,
        'importe_ars' => 10000, 'medio' => 'efectivo', 'clave_idempotencia' => (string) Str::uuid(),
    ];
});

it('conserva el ingreso parcial sus relaciones y el medio sin alterar cargos', function (string $medio): void {
    $pago = PagoPlanilla::create(array_replace($this->datos, ['medio' => $medio]))->fresh();

    expect($pago->importe_ars)->toBeInt()->toBe(10000)
        ->and($pago->medio)->toBe($medio)
        ->and($pago->cuenta->is($this->cuenta))->toBeTrue()
        ->and($pago->cargoOrigen->is($this->cargo))->toBeTrue()
        ->and($pago->usuario->is($this->admin))->toBeTrue()
        ->and($this->cargo->fresh()->precio_original)->toBe(25000);
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
})->with(['efectivo', 'mercado_pago']);

it('rechaza recibos nuevos por transferencia', function (): void {
    expect(fn () => PagoPlanilla::create(array_replace($this->datos, ['medio' => 'transferencia'])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
});

it('congela fecha servidor y nombre vigente ignorando snapshots suministrados', function (): void {
    $this->travelTo(Carbon::parse('2026-10-08 10:30:00'));
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);
    $pago = new PagoPlanilla($this->datos);
    $pago->forceFill(['recibido_at' => '2000-01-01 00:00:00', 'actor_nombre' => 'Nombre Falso']);
    $pago->save();
    $pago = $pago->fresh();

    expect($pago->recibido_at->format('Y-m-d H:i:s'))->toBe('2026-10-08 10:30:00')
        ->and($pago->recibido_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($pago->actor_nombre)->toBe('Nombre Vigente');
});

it('rechaza importes invalidos antes del cast sin registrar ingresos', function (mixed $importe): void {
    expect(fn () => PagoPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe])))
        ->toThrow(LogicException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
})->with([0, -1, 1.5, true, false, '10000', null, 2147483648]);

it('acepta limites enteros SQL sin limitar el ingreso al precio de la planilla', function (int $importe): void {
    expect(PagoPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe]))->fresh()->importe_ars)->toBe($importe);
})->with([1, 2147483647]);

it('rechaza medios y claves ajenos al contrato', function (string $campo, mixed $valor): void {
    expect(fn () => PagoPlanilla::create(array_replace($this->datos, [$campo => $valor])))
        ->toThrow(LogicException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
})->with([
    ['medio', 'cheque'], ['medio', null], ['medio', true],
    ['clave_idempotencia', ''], ['clave_idempotencia', 'no-uuid'], ['clave_idempotencia', null],
]);

it('impide reutilizar una clave incluso al insertar directamente en SQL', function (): void {
    $pago = PagoPlanilla::create($this->datos);
    $duplicado = $pago->getAttributes();
    unset($duplicado['id']);
    $equipo = crearEquipoCargos($this->contexto, 'Cuenta Distinta');
    $duplicado['cuenta_id'] = CuentaCobroPlanilla::create(['idEquipo' => $equipo->id, 'idEdicion' => $this->contexto['edicion']->id])->id;

    expect(fn () => DB::table('pago_planillas')->insert($duplicado))->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('pago_planillas', 1);
});

it('normaliza UUID uppercase y rechaza su repeticion mediante Eloquent', function (): void {
    $this->datos['clave_idempotencia'] = 'A0A2A2D2-0B87-4A18-83F2-2529882BE2DE';
    $pago = PagoPlanilla::create($this->datos)->fresh();

    expect($pago->clave_idempotencia)->toBe(strtolower($this->datos['clave_idempotencia']));
    expect(fn () => PagoPlanilla::create($this->datos))->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('pago_planillas', 1);
});

it('rechaza referencias financieras ausentes o de otro equipo o edicion', function (string $caso): void {
    $datos = $this->datos;
    if ($caso === 'cuenta ausente' || $caso === 'cargo ausente') {
        $datos[$caso === 'cuenta ausente' ? 'cuenta_id' : 'cargo_origen_id'] = 2147483647;
    } else {
        $contexto = $caso === 'otra edicion' ? crearContextoCargos('Otra Edicion Pago') : $this->contexto;
        $equipo = $caso === 'otra edicion' ? $this->equipo : crearEquipoCargos($contexto, 'Otra Cuenta Pago');
        if ($caso === 'otra edicion') {
            EquipoEdicion::create(['idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]);
        }
        $datos['cuenta_id'] = CuentaCobroPlanilla::create(['idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id])->id;
    }

    expect(fn () => PagoPlanilla::create($datos))->toThrow(LogicException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
})->with(['cuenta ausente', 'cargo ausente', 'otro equipo', 'otra edicion']);

it('rechaza referencias internas ambiguas sin convertirlas silenciosamente', function (string $campo, mixed $valor): void {
    expect(fn () => PagoPlanilla::create(array_replace($this->datos, [$campo => $valor])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
})->with(['cuenta_id', 'cargo_origen_id', 'usuario_id'])->with([true, null, '1', 0, -1]);

it('rechaza operadores inexistentes o con rol revocado', function (bool $borrar): void {
    if ($borrar) {
        User::query()->whereKey($this->admin->id)->delete();
    } else {
        User::query()->whereKey($this->admin->id)->update(['rol' => 'regular']);
    }

    expect(fn () => PagoPlanilla::create($this->datos))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('pago_planillas', 0);
})->with([true, false]);

it('no permite editar o borrar el recibo por instancia', function (bool $borrar): void {
    $pago = PagoPlanilla::create($this->datos);
    expect(fn () => $borrar ? $pago->delete() : $pago->update(['importe_ars' => 1]))->toThrow(LogicException::class);
    $this->assertDatabaseHas('pago_planillas', ['id' => $pago->id, 'importe_ars' => 10000]);
})->with([true, false]);

it('conserva el ingreso y el nombre historico al borrar el operador', function (): void {
    $pago = PagoPlanilla::create($this->datos);
    User::query()->whereKey($this->admin->id)->delete();

    expect($pago->fresh()->usuario_id)->toBeNull()
        ->and($pago->fresh()->usuario)->toBeNull()
        ->and($pago->fresh()->actor_nombre)->toBe('Operador Original')
        ->and($pago->fresh()->importe_ars)->toBe(10000);
});

it('impide borrar referencias financieras usadas incluso mediante SQL', function (string $tabla): void {
    $pago = PagoPlanilla::create($this->datos);
    $id = $tabla === 'cargo_planillas' ? $this->cargo->id : $this->cuenta->id;

    expect(fn () => DB::table($tabla)->where('id', $id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('pago_planillas', ['id' => $pago->id]);
})->with(['cargo_planillas', 'cuenta_cobro_planillas']);
