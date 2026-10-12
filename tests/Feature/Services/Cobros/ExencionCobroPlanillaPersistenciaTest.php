<?php

use App\Models\CargoPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\PagosCobroService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Exenciones');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival Exenciones');
    $this->partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));
    $this->datosCargo = [
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $this->partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ];
    $this->cargo = CargoPlanilla::create($this->datosCargo);
    $this->admin = crearAdminCargos();
    $this->datos = ['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 5000];
});

it('persiste el importe historico y relaciones sin activar efectos financieros', function (): void {
    $exencion = ExencionCobroPlanilla::create($this->datos)->fresh();

    expect($exencion->importe_ars)->toBeInt()->toBe(5000)
        ->and($exencion->cargo())->toBeInstanceOf(BelongsTo::class)
        ->and($exencion->usuario())->toBeInstanceOf(BelongsTo::class)
        ->and($exencion->cargo->is($this->cargo))->toBeTrue()
        ->and($exencion->usuario->is($this->admin))->toBeTrue()
        ->and($exencion->actor_nombre)->toBe('Admin Cobros')
        ->and($exencion->motivo)->toBeNull()
        ->and($this->cargo->fresh()->precio_original)->toBe(25000);
    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
    $this->assertDatabaseHas('configuracion_cobro_planillas', ['id' => 1, 'activado_at' => null]);
});

it('preserva filas financieras completas de un cargo con pago parcial previo', function (): void {
    activarCobrosCargos();
    $pago = app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->assertDatabaseHas('pago_planillas', ['id' => $pago->id, 'cargo_origen_id' => $this->cargo->id, 'importe_ars' => 10000]);
    $this->assertDatabaseHas('aplicacion_pago_planillas', ['pago_id' => $pago->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 10000]);
    $this->assertDatabaseHas('historial_cobro_planillas', ['accion' => 'registrar_pago', 'usuario_id' => $this->admin->id]);

    $leerFilas = fn (string $tabla): array => DB::table($tabla)->orderBy('id')->get()
        ->map(fn (object $fila): array => (array) $fila)->all();
    $antes = [];
    foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'] as $tabla) {
        $antes[$tabla] = $leerFilas($tabla);
        expect($antes[$tabla])->not->toBeEmpty();
    }
    $pendiente = $this->cargo->precio_original
        - (int) DB::table('aplicacion_pago_planillas')->where('cargo_id', $this->cargo->id)->sum('importe_ars');
    expect($pendiente)->toBe(15000);

    $exencion = ExencionCobroPlanilla::create(array_replace($this->datos, ['importe_ars' => $pendiente]))->fresh();

    foreach ($antes as $tabla => $filas) {
        expect($leerFilas($tabla))->toBe($filas);
    }
    $this->assertDatabaseCount('exencion_cobro_planillas', 1);
    $this->assertDatabaseHas('exencion_cobro_planillas', ['id' => $exencion->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 15000]);
    expect($exencion->cargo->is($this->cargo))->toBeTrue()
        ->and((int) $exencion->cargo->idEquipo)->toBe($this->equipo->id)
        ->and((int) $exencion->cargo->idEquipo)->not->toBe($this->rival->id);
});

it('acepta motivo opcional y extremos del importe por fila', function (?string $motivo, int $importe): void {
    $exencion = ExencionCobroPlanilla::create(array_replace($this->datos, ['motivo' => $motivo, 'importe_ars' => $importe]))->fresh();

    expect($exencion->motivo)->toBe($motivo)->and($exencion->importe_ars)->toBe($importe);
})->with([null, '', 'Acuerdo con el equipo'])->with([1, 25000]);

it('acepta el maximo INT firmado cuando el precio original lo admite', function (): void {
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));
    $cargo = CargoPlanilla::create(array_replace($this->datosCargo, ['precio_original' => 2147483647, 'parent_id' => $partido->id]));
    $exencion = ExencionCobroPlanilla::create(array_replace($this->datos, ['cargo_id' => $cargo->id, 'importe_ars' => 2147483647]));

    expect($exencion->fresh()->importe_ars)->toBe(2147483647);
});

it('rechaza importes ambiguos o superiores al precio original', function (mixed $importe): void {
    expect(fn () => ExencionCobroPlanilla::create(array_replace($this->datos, ['importe_ars' => $importe])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
})->with([0, -1, true, false, null, '5000', 5000.0, 1.5, 25001, 2147483648]);

it('rechaza identificadores internos ambiguos', function (string $campo, mixed $valor): void {
    expect(fn () => ExencionCobroPlanilla::create(array_replace($this->datos, [$campo => $valor])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
})->with(['cargo_id', 'usuario_id'])->with([true, false, null, '1', 1.0, 1.5, 0, -1]);

it('requiere un cargo existente', function (): void {
    expect(fn () => ExencionCobroPlanilla::create(array_replace($this->datos, ['cargo_id' => 2147483647])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
});

it('requiere un administrador existente', function (string $rol): void {
    $id = $rol === 'ausente' ? 2147483647 : crearUsuarioConRolCargos($rol)->id;
    expect(fn () => ExencionCobroPlanilla::create(array_replace($this->datos, ['usuario_id' => $id])))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
})->with(['editor', 'regular', 'ausente']);

it('valida el rol vigente aunque conserve una instancia admin obsoleta', function (): void {
    User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);
    expect($this->admin->isAdmin())->toBeTrue();
    expect(fn () => ExencionCobroPlanilla::create($this->datos))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
});

it('toma nombre vigente y fecha del servidor ignorando snapshots falsificados', function (): void {
    $this->travelTo(Carbon::parse('2026-10-09 12:00:00'));
    User::query()->whereKey($this->admin->id)->update(['name' => 'Admin Vigente']);
    $exencion = new ExencionCobroPlanilla($this->datos);
    $exencion->setRelation('usuario', $this->admin);
    $exencion->forceFill(['actor_nombre' => 'Falso', 'created_at' => '2000-01-01 00:00:00'])->save();
    User::query()->whereKey($this->admin->id)->update(['name' => 'Otro Nombre']);
    $exencion = $exencion->fresh();

    expect($exencion->actor_nombre)->toBe('Admin Vigente')
        ->and($exencion->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($exencion->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-09 12:00:00')
        ->and($exencion->getAttributes())->not->toHaveKey('updated_at');
});

it('rechaza motivos que no sean texto ni null', function (mixed $motivo): void {
    expect(fn () => ExencionCobroPlanilla::create(array_replace($this->datos, ['motivo' => $motivo])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
})->with([true, 1, 1.5, [[]]]);

it('garantiza una unica exencion por cargo mediante SQL', function (): void {
    $exencion = ExencionCobroPlanilla::create($this->datos);
    expect(fn () => ExencionCobroPlanilla::create($this->datos))->toThrow(QueryException::class);
    $datos = $exencion->getAttributes();
    unset($datos['id']);
    expect(fn () => DB::table('exencion_cobro_planillas')->insert($datos))->toThrow(QueryException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 1);
});

it('limita cada registro al cargo del equipo sin eximir al rival ni otros cargos', function (): void {
    ExencionCobroPlanilla::create($this->datos);
    $rival = CargoPlanilla::create(array_replace($this->datosCargo, ['idEquipo' => $this->rival->id]));
    $otroPartido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));
    $otroCargo = CargoPlanilla::create(array_replace($this->datosCargo, ['parent_id' => $otroPartido->id]));
    expect(ExencionCobroPlanilla::query()->whereIn('cargo_id', [$rival->id, $otroCargo->id])->exists())->toBeFalse();
    ExencionCobroPlanilla::create(array_replace($this->datos, ['cargo_id' => $otroCargo->id, 'importe_ars' => 1]));
    $this->assertDatabaseCount('exencion_cobro_planillas', 2);
});

it('no permite editar ni borrar por instancia y conserva toda la fila', function (bool $borrar): void {
    $exencion = ExencionCobroPlanilla::create($this->datos)->fresh();
    $original = $exencion->getAttributes();
    expect(fn () => $borrar ? $exencion->delete() : $exencion->update(['importe_ars' => 1, 'created_at' => now()->subYear()]))->toThrow(LogicException::class);
    expect($exencion->fresh()->getAttributes())->toBe($original);
})->with([true, false]);

it('restringe borrado SQL del cargo y conserva la exencion', function (): void {
    ExencionCobroPlanilla::create($this->datos);
    expect(fn () => DB::table('cargo_planillas')->where('id', $this->cargo->id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('cargo_planillas', ['id' => $this->cargo->id]);
    $this->assertDatabaseCount('exencion_cobro_planillas', 1);
});

it('rechaza referencias huerfanas mediante SQL', function (string $campo): void {
    $datos = array_replace($this->datos, [$campo => 2147483647, 'actor_nombre' => 'Admin Cobros', 'created_at' => now()]);
    expect(fn () => DB::table('exencion_cobro_planillas')->insert($datos))->toThrow(QueryException::class);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
})->with(['cargo_id', 'usuario_id']);

it('conserva actor importe y fecha cuando se elimina el usuario mediante SQL', function (): void {
    $exencion = ExencionCobroPlanilla::create($this->datos)->fresh();
    $original = $exencion->getAttributes();
    DB::table('users')->where('id', $this->admin->id)->delete();
    $exencion = $exencion->fresh();

    expect($exencion->usuario_id)->toBeNull()->and($exencion->usuario)->toBeNull()
        ->and($exencion->getAttributes())->toBe(array_replace($original, ['usuario_id' => null]));
});
