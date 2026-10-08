<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\Equipo;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

require_once __DIR__.'/FixturesCargos.php';

function crearCargoPagos(array $contexto, Equipo $equipo, int $precio = 25000): CargoPlanilla
{
    $rival = crearEquipoCargos($contexto, 'Rival Pagos');
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio, 'snapshot' => [],
    ]);
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Pagos');
    $this->cargo = crearCargoPagos($this->contexto, $this->equipo);
    $this->admin = crearAdminCargos('Operador Pagos');
    activarCobrosCargos();
    $this->servicio = new PagosCobroService;
    $this->clave = 'a1111111-1111-4111-8111-111111111111';
});

it('registra un pago parcial con aplicacion minima auditoria unica y credito cero', function (): void {
    $pago = $this->servicio->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->clave)->fresh();

    expect($pago->importe_ars)->toBe(10000)
        ->and($pago->medio)->toBe('efectivo')
        ->and($pago->clave_idempotencia)->toBe($this->clave)
        ->and($pago->cuenta->idEquipo)->toBe($this->equipo->id)
        ->and($pago->cargoOrigen->is($this->cargo))->toBeTrue();
    expect((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(10000);
    $evento = HistorialCobroPlanilla::query()->sole();
    expect($evento->accion)->toBe('registrar_pago')
        ->and($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->actor_nombre)->toBe('Operador Pagos')
        ->and($evento->valores_antes)->toBe(['cargo_id' => $this->cargo->id, 'pendiente_ars' => 25000])
        ->and($evento->valores_despues['pago_id'])->toBe($pago->id)
        ->and($evento->valores_despues['aplicacion_id'])->toBe((int) AplicacionPagoPlanilla::query()->value('id'))
        ->and($evento->valores_despues['importe_aplicado_ars'])->toBe(10000)
        ->and($evento->valores_despues['credito_ars'])->toBe(0)
        ->and($evento->valores_despues['ingreso_ars'])->toBe(10000);
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('historial_cobro_planillas', 1);
});

it('aplica el importe exacto y salda el cargo sin credito', function (): void {
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 25000, 'transferencia', $this->clave);

    $evento = HistorialCobroPlanilla::query()->sole();
    expect((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(25000)
        ->and($evento->valores_antes['pendiente_ars'])->toBe(25000)
        ->and($evento->valores_despues['credito_ars'])->toBe(0);
});

it('retiene el excedente en el mismo pago y cuenta sin otro ingreso ni aplicaciones extra', function (): void {
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 30000, 'mercado_pago', $this->clave);

    expect(PagoPlanilla::query()->sole()->importe_ars)->toBe(30000)
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(25000)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->sole()->valores_despues['credito_ars'])->toBe(5000);
});

it('acumula varios pagos sobre el cargo sin superar nunca el precio original', function (): void {
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 15000, 'efectivo', $this->clave);
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 15000, 'efectivo', '22222222-2222-4222-8222-222222222222');

    expect((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(25000)
        ->and((int) PagoPlanilla::query()->sum('importe_ars'))->toBe(30000);
    $this->assertDatabaseCount('historial_cobro_planillas', 2);
});

it('rechaza un pago nuevo sobre un cargo ya saldado sin anticipos ni efectos nuevos', function (): void {
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 25000, 'efectivo', $this->clave);

    expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 1000, 'efectivo', '33333333-3333-4333-8333-333333333333'))
        ->toThrow(ValidationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
});

it('reintenta la misma clave global y devuelve el pago original sin reaplicar ni reauditar', function (): void {
    $original = $this->servicio->registrarPago($this->admin, $this->cargo->id, 25000, 'efectivo', $this->clave);
    $reintento = $this->servicio->registrarPago($this->admin, $this->cargo->id, 25000, 'efectivo', strtoupper($this->clave));

    expect($reintento->is($original))->toBeTrue()
        ->and($reintento->clave_idempotencia)->toBe($this->clave)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
});

it('rechaza la clave reutilizada con otro payload sin ningun ingreso nuevo', function (string $cambio): void {
    $this->servicio->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->clave);
    $otroCargo = crearCargoPagos($this->contexto, $this->equipo);
    $otroAdmin = crearAdminCargos('Operador Alterno');
    [$cargoId, $importe, $medio, $actor] = match ($cambio) {
        'cargo' => [$otroCargo->id, 10000, 'efectivo', $this->admin],
        'importe' => [$this->cargo->id, 9999, 'efectivo', $this->admin],
        'medio' => [$this->cargo->id, 10000, 'transferencia', $this->admin],
        'actor' => [$this->cargo->id, 10000, 'efectivo', $otroAdmin],
    };

    expect(fn () => $this->servicio->registrarPago($actor, $cargoId, $importe, $medio, $this->clave))
        ->toThrow(ValidationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
})->with(['cargo', 'importe', 'medio', 'actor']);

it('rechaza actores que no son operadores persistidos y vigentes', function (string $caso): void {
    $actor = match ($caso) {
        'transitorio' => (new User)->forceFill(['id' => $this->admin->id, 'rol' => 'admin']),
        'pk_fingida' => tap($this->admin, fn ($u) => $u->forceFill(['id' => 999999999])),
        'regular' => crearUsuarioConRolCargos('regular'),
        'revocado' => tap($this->admin, fn () => User::query()->whereKey($this->admin->id)->update(['rol' => 'editor'])),
        'borrado' => tap($this->admin, fn () => User::query()->whereKey($this->admin->id)->delete()),
    };

    expect(fn () => $this->servicio->registrarPago($actor, $this->cargo->id, 1000, 'efectivo', $this->clave))
        ->toThrow(AuthorizationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['transitorio', 'pk_fingida', 'regular', 'revocado', 'borrado']);

it('audita el nombre fresco del operador vigente y no el del objeto actor', function (): void {
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);

    $this->servicio->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->clave);

    expect(PagoPlanilla::query()->sole()->actor_nombre)->toBe('Nombre Vigente')
        ->and(HistorialCobroPlanilla::query()->sole()->actor_nombre)->toBe('Nombre Vigente');
});

it('falla cerrado sin configuracion global activa sin crear cuenta ni ingresos', function (string $caso): void {
    $caso === 'ausente'
        ? ConfiguracionCobroPlanilla::query()->whereKey(1)->delete()
        : ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

    expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 1000, 'efectivo', $this->clave))
        ->toThrow(RuntimeException::class)
        ->and(CuentaCobroPlanilla::query()->count())->toBe(0)
        ->and(PagoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['ausente', 'inactiva']);

it('rechaza un cargo inexistente sin derivar cuenta', function (): void {
    expect(fn () => $this->servicio->registrarPago($this->admin, 2147483647, 1000, 'efectivo', $this->clave))
        ->toThrow(ValidationException::class)
        ->and(CuentaCobroPlanilla::query()->count())->toBe(0)
        ->and(PagoPlanilla::query()->count())->toBe(0);
});

it('rechaza importes que no son enteros estrictos dentro del rango ARS', function (mixed $importe): void {
    expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, $importe, 'efectivo', $this->clave))
        ->toThrow(ValidationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([0, -1, 1.5, true, '25000', null, 2147483648]);

it('rechaza medios fuera de los tres codigos en minusculas', function (string $medio): void {
    expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 1000, $medio, $this->clave))
        ->toThrow(ValidationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(0);
})->with(['cheque', 'EFECTIVO', 'Efectivo', '', 'mercado pago']);

it('rechaza claves de idempotencia que no son UUID validos', function (string $clave): void {
    expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 1000, 'efectivo', $clave))
        ->toThrow(ValidationException::class)
        ->and(PagoPlanilla::query()->count())->toBe(0);
})->with(['', 'no-uuid', '11111111-1111-1111-1111-11111111111']);

it('usa lecturas bloqueadas para identidad UUID y deuda dentro de una transaccion externa', function (): void {
    $consultas = [];
    $conexion = DB::connection();
    $dispatcher = $conexion->getEventDispatcher();
    $conexion->setEventDispatcher(clone $dispatcher);
    try {
        $conexion->listen(function (QueryExecuted $consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });
        DB::transaction(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->clave));
        foreach (['clave_idempotencia', 'sum(', 'cuenta_cobro_planillas'] as $fragmento) {
            $sql = collect($consultas)->first(fn (string $sql): bool => str_contains($sql, $fragmento));
            expect($sql)->toBeString()->toContain('for update');
        }
    } finally {
        $conexion->setEventDispatcher($dispatcher);
    }
});

it('revierte atomicamente si un creating hook veta pago aplicacion o historial', function (string $clase): void {
    $clase::creating(function (): bool {
        return false;
    });

    try {
        expect(fn () => $this->servicio->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->clave))
            ->toThrow(RuntimeException::class)
            ->and(PagoPlanilla::query()->count())->toBe(0)
            ->and(AplicacionPagoPlanilla::query()->count())->toBe(0)
            ->and(HistorialCobroPlanilla::query()->count())->toBe(0)
            ->and(CuentaCobroPlanilla::query()->count())->toBe(0);
    } finally {
        $clase::flushEventListeners();
        $clase::clearBootedModels();
    }
})->with([PagoPlanilla::class, AplicacionPagoPlanilla::class, HistorialCobroPlanilla::class]);
