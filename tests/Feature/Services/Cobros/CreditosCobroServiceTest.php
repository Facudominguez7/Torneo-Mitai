<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Equipo;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

require_once __DIR__.'/FixturesCargos.php';

function crearCargoCredito(array $contexto, Equipo $equipo, int $precio, ?string $horario = '2026-04-05 10:00:00', bool $final = false): CargoPlanilla
{
    $rival = crearEquipoCargos($contexto, 'Rival Credito');
    if ($final) {
        $instancia = InstanciaFinal::create(crearDatosFinalCargos($contexto, $equipo, $rival, ['horario' => $horario]));
        $padre = ['tipo' => CargoPlanilla::TIPO_INSTANCIA_FINAL, 'parent_type' => InstanciaFinal::class, 'parent_id' => $instancia->id];
    } else {
        $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));
        $padre = ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id];
    }

    return CargoPlanilla::create($padre + [
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio, 'snapshot' => [],
    ]);
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Credito');
    $this->admin = crearAdminCargos('Operador Credito');
    activarCobrosCargos();
    $this->origen = crearCargoCredito($this->contexto, $this->equipo, 10000);
    $this->pago = (new PagosCobroService)->registrarPago($this->admin, $this->origen->id, 30000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $this->servicio = new CreditosCobroService;
    $this->clave = 'ca11a111-1111-41a1-8a1a-111111111111';
});

it('omite elegido eximido distribuye residual FIFO y carga exenciones en lote bloqueado', function (): void {
    $elegido = crearCargoCredito($this->contexto, $this->equipo, 5000);
    $temprano = crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-03-01', true);
    $tarde = crearCargoCredito($this->contexto, $this->equipo, 4000, '2026-05-01');
    (new PagosCobroService)->registrarPago($this->admin, $temprano->id, 2000, 'efectivo', '22222222-2222-4222-8222-222222222222');
    foreach ([$elegido->id => 5000, $temprano->id => 3000] as $id => $importe) {
        ExencionCobroPlanilla::create(['cargo_id' => $id, 'usuario_id' => $this->admin->id, 'importe_ars' => $importe]);
    }
    $rival = CargoPlanilla::create(array_replace($elegido->only(['tipo', 'parent_type', 'parent_id', 'idEdicion', 'idCategoria', 'precio_original', 'snapshot']), [
        'idEquipo' => crearEquipoCargos($this->contexto, 'Rival Eximido')->id,
    ]));
    ExencionCobroPlanilla::create(['cargo_id' => $rival->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 5000]);
    DB::table('cargo_planillas')->where('id', $elegido->id)->update(['parent_id' => 2147483647]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $elegido->id, $this->clave);
    $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_contains($consulta['query'], 'exencion_cobro_planillas'))->values();
    DB::disableQueryLog();
    $lote = $consultas->filter(fn (array $consulta): bool => str_contains($consulta['query'], ' in ('))->values();
    expect($consultas)->toHaveCount(3);
    expect($lote)->toHaveCount(1)
        ->and($lote[0]['query'])->toContain('order by `cargo_id` asc', 'for update')
        ->and($lote[0]['bindings'])->toBe([$this->origen->id, $elegido->id, $temprano->id, $tarde->id]);
    $aplicaciones = AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', '!=', $this->origen->id)->orderBy('id')->get();
    expect($aplicaciones->pluck('cargo_id')->all())->toBe([$temprano->id, $tarde->id])
        ->and($aplicaciones->pluck('importe_ars')->all())->toBe([3000, 4000])
        ->and($evento->valores_antes['credito_ars'])->toBe(20000)
        ->and($evento->valores_despues['credito_restante_ars'])->toBe(13000)
        ->and($this->pago->fresh()->importe_ars)->toBe(30000)
        ->and(AplicacionPagoPlanilla::query()->where('cargo_id', $rival->id)->count())->toBe(0);
    expect($this->servicio->aplicarCredito($this->admin, $this->pago->id, $elegido->id, $this->clave)->is($evento))->toBeTrue();
});

it('conserva credito y filas financieras cuando todos quedan eximidos', function (): void {
    $cargo = crearCargoCredito($this->contexto, $this->equipo, 5000);
    ExencionCobroPlanilla::create(['cargo_id' => $cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 5000]);
    DB::table('cargo_planillas')->where('id', $cargo->id)->update(['parent_id' => 2147483647]);
    $tablas = ['pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'];
    $antes = collect($tablas)->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->orderBy('id')->get()->all()]);
    expect(fn () => $this->servicio->aplicarCredito($this->admin, $this->pago->id, $cargo->id, $this->clave))->toThrow(ValidationException::class);
    foreach ($antes as $tabla => $filas) {
        expect(DB::table($tabla)->orderBy('id')->get()->all())->toEqual($filas);
    }
});

it('exige padre vigente para residual eximido y revierte sin tocar credito', function (): void {
    $cargo = crearCargoCredito($this->contexto, $this->equipo, 5000);
    ExencionCobroPlanilla::create(['cargo_id' => $cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 3000]);
    DB::table('cargo_planillas')->where('id', $cargo->id)->update(['parent_id' => 2147483647]);
    expect(fn () => $this->servicio->aplicarCredito($this->admin, $this->pago->id, $cargo->id, $this->clave))->toThrow(RuntimeException::class);
    expect((int) AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->sum('importe_ars'))->toBe(10000);
    $this->assertDatabaseCount('historial_cobro_planillas', 1);
});

it('falla cerrado ante exencion con aplicaciones sobre el precio sin consumir credito', function (): void {
    ExencionCobroPlanilla::create(['cargo_id' => $this->origen->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 1]);
    expect(fn () => $this->servicio->aplicarCredito($this->admin, $this->pago->id, $this->origen->id, $this->clave))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 1);
    $this->assertDatabaseCount('historial_cobro_planillas', 1);
});

it('aplica el credito saldando primero el cargo elegido con tope parcial y remanente sin otro ingreso', function (): void {
    $elegido = crearCargoCredito($this->contexto, $this->equipo, 12000, '2026-04-20 09:00:00');
    $temprano = crearCargoCredito($this->contexto, $this->equipo, 4000, '2026-04-01 09:00:00', true);

    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $elegido->id, $this->clave)->fresh();

    $aplicaciones = AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', '!=', $this->origen->id)->orderBy('id')->get();
    expect($aplicaciones->pluck('cargo_id')->all())->toBe([$elegido->id, $temprano->id])
        ->and($aplicaciones->pluck('importe_ars')->all())->toBe([12000, 4000])
        ->and($evento->accion)->toBe('aplicar_credito')
        ->and($evento->valores_antes['credito_ars'])->toBe(20000)
        ->and($evento->valores_despues['importe_aplicado_ars'])->toBe(16000)
        ->and($evento->valores_despues['credito_restante_ars'])->toBe(4000)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(PagoPlanilla::query()->sole()->importe_ars)->toBe(30000);
});

it('reescribe el FIFO con la fecha vigente cambiada en caliente saldando el primero completo y el segundo parcial', function (): void {
    $a = crearCargoCredito($this->contexto, $this->equipo, 15000, '2026-04-01 09:00:00', true);
    $b = crearCargoCredito($this->contexto, $this->equipo, 15000, '2026-05-01 09:00:00');
    Partido::query()->whereKey($b->parent_id)->update(['horario_datetime' => '2026-03-01 09:00:00']);

    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $this->origen->id, $this->clave);

    $aplicaciones = AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', '!=', $this->origen->id)->orderBy('id')->get();
    expect($aplicaciones->pluck('cargo_id')->all())->toBe([$b->id, $a->id])
        ->and($aplicaciones->pluck('importe_ars')->all())->toBe([15000, 5000])
        ->and($evento->valores_despues['credito_restante_ars'])->toBe(0)
        ->and((int) AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->sum('importe_ars'))->toBe(30000)
        ->and(PagoPlanilla::query()->count())->toBe(1);
});

it('saltea el cargo elegido ya saldado y aplica a los demas deudores con remanente', function (): void {
    $saldado = crearCargoCredito($this->contexto, $this->equipo, 5000, '2026-04-01 09:00:00');
    (new PagosCobroService)->registrarPago($this->admin, $saldado->id, 5000, 'efectivo', '22222222-2222-4222-8222-222222222222');
    $otro = crearCargoCredito($this->contexto, $this->equipo, 6000, '2026-04-02 09:00:00', true);

    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $saldado->id, $this->clave);

    expect(AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', $saldado->id)->count())->toBe(0)
        ->and(AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', $otro->id)->value('importe_ars'))->toBe(6000)
        ->and($evento->valores_despues['credito_restante_ars'])->toBe(14000);
});

it('aplica solo el credito disponible cuando la deuda elegida lo supera y no excede el precio original', function (): void {
    $endeudado = crearCargoCredito($this->contexto, $this->equipo, 25000, '2026-04-01 09:00:00');

    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $endeudado->id, $this->clave);

    expect(AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->where('cargo_id', $endeudado->id)->value('importe_ars'))->toBe(20000)
        ->and($evento->valores_despues['importe_aplicado_ars'])->toBe(20000)
        ->and($evento->valores_despues['credito_restante_ars'])->toBe(0)
        ->and((int) AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->sum('importe_ars'))->toBe(30000);
});

it('devuelve el evento original en replay con clave en mayusculas aun con deudas nuevas', function (): void {
    $destino = crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-04-01 09:00:00');
    $original = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, $this->clave);
    crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-03-01 09:00:00');

    $replay = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, strtoupper($this->clave));

    expect($replay->is($original))->toBeTrue()
        ->and($replay->valores_antes['clave_idempotencia'])->toBe($this->clave)
        ->and($replay->valores_despues['importe_aplicado_ars'])->toBe(8000)
        ->and(AplicacionPagoPlanilla::query()->where('pago_id', $this->pago->id)->count())->toBe(2)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1);
});

it('rechaza la clave de credito reutilizada con otra tripleta sin mutar nada', function (string $cambio): void {
    $destino = crearCargoCredito($this->contexto, $this->equipo, 8000);
    $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, $this->clave);
    $otroCargo = crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-04-02 09:00:00');
    $otroOrigen = crearCargoCredito($this->contexto, $this->equipo, 10000, '2026-04-03 09:00:00');
    $otroPago = (new PagosCobroService)->registrarPago($this->admin, $otroOrigen->id, 12000, 'efectivo', '33333333-3333-4333-8333-333333333333');
    $otroAdmin = crearAdminCargos('Operador Alterno');
    [$pagoId, $cargoId, $actor] = match ($cambio) {
        'cargo' => [$this->pago->id, $otroCargo->id, $this->admin],
        'pago' => [$otroPago->id, $destino->id, $this->admin],
        'actor' => [$this->pago->id, $destino->id, $otroAdmin],
    };

    expect(fn () => $this->servicio->aplicarCredito($actor, $pagoId, $cargoId, $this->clave))->toThrow(ValidationException::class)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(3)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1);
})->with(['cargo', 'pago', 'actor']);

it('rechaza una huella malformada con uuid coincidente sin re-aplicar el credito', function (): void {
    $destino = crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-04-01 09:00:00');
    HistorialCobroPlanilla::create([
        'accion' => 'aplicar_credito',
        'usuario_id' => (int) $this->admin->id,
        'actor_nombre' => $this->admin->name,
        'valores_antes' => ['pago_id' => (string) $this->pago->id, 'cargo_id' => (int) $destino->id, 'clave_idempotencia' => $this->clave],
        'valores_despues' => ['aplicaciones' => [], 'importe_aplicado_ars' => 0, 'credito_restante_ars' => 0],
    ]);

    expect(fn () => $this->servicio->aplicarCredito($this->admin, (int) $this->pago->id, (int) $destino->id, $this->clave))->toThrow(ValidationException::class)
        ->and(AplicacionPagoPlanilla::query()->where('pago_id', (int) $this->pago->id)->where('cargo_id', (int) $destino->id)->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1);
});

it('falla cerrado en escenarios de validacion sin aplicaciones ni auditoria nuevas', function (string $caso): void {
    $cargo = crearCargoCredito($this->contexto, $this->equipo, 8000, '2026-04-01 09:00:00');
    $preparar = match ($caso) {
        'sin_credito' => function () use ($cargo) {
            $pago = (new PagosCobroService)->registrarPago($this->admin, $cargo->id, 8000, 'efectivo', '44444444-4444-4444-8444-444444444444');

            return [ValidationException::class, (int) $pago->id, (int) $cargo->id, 2];
        },
        'sin_deuda' => function () use ($cargo) {
            (new PagosCobroService)->registrarPago($this->admin, $cargo->id, 8000, 'efectivo', '44444444-4444-4444-8444-444444444444');

            return [ValidationException::class, (int) $this->pago->id, (int) $cargo->id, 2];
        },
        'cargo_otra_edicion' => function () {
            $contexto = crearContextoCargos('Otra Edicion');
            $cargo = crearCargoCredito($contexto, crearEquipoCargos($contexto, 'Otro Equipo'), 5000);

            return [ValidationException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'cargo_otro_equipo' => function () {
            $cargoAjeno = crearCargoCredito($this->contexto, crearEquipoCargos($this->contexto, 'Equipo Ajeno'), 5000);

            return [ValidationException::class, (int) $this->pago->id, (int) $cargoAjeno->id, 1];
        },
        'cargo_inexistente' => fn (): array => [ValidationException::class, (int) $this->pago->id, 2147483647, 1],
        'clave_invalida' => function () use ($cargo) {
            $this->clave = 'no-uuid';

            return [ValidationException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'credito_corrupto' => function () use ($cargo) {
            DB::table('aplicacion_pago_planillas')->insert(['pago_id' => $this->pago->id, 'cargo_id' => $cargo->id, 'importe_ars' => 999999999, 'created_at' => now()]);

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 2];
        },
        'deuda_corrupta' => function () use ($cargo) {
            $portador = crearCargoCredito($this->contexto, $this->equipo, 20000, '2026-04-04 09:00:00');
            $otroPago = (new PagosCobroService)->registrarPago($this->admin, $portador->id, 5000, 'efectivo', '45555555-5555-4555-8555-555555555555');
            DB::table('aplicacion_pago_planillas')->insert(['pago_id' => $otroPago->id, 'cargo_id' => $cargo->id, 'importe_ars' => 999999999, 'created_at' => now()]);

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 3];
        },
        'padre_borrado' => function () use ($cargo) {
            Partido::query()->whereKey($cargo->parent_id)->delete();

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'padre_foraneo' => function () use ($cargo) {
            CargoPlanilla::query()->whereKey($cargo->id)->update(['parent_type' => 'ClaseFinancieraInexistente']);

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'config_ausente' => function () use ($cargo) {
            ConfiguracionCobroPlanilla::query()->whereKey(1)->delete();

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'config_inactiva' => function () use ($cargo) {
            ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

            return [RuntimeException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'actor_revocado' => function () use ($cargo) {
            User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);

            return [AuthorizationException::class, (int) $this->pago->id, (int) $cargo->id, 1];
        },
        'actor_borrado' => function () {
            User::query()->whereKey($this->admin->id)->delete();

            return [AuthorizationException::class, (int) $this->pago->id, (int) $this->origen->id, 1];
        },
    };

    [$excepcion, $pagoId, $cargoId, $aplicaciones] = $preparar();

    expect(fn () => $this->servicio->aplicarCredito($this->admin, $pagoId, $cargoId, $this->clave))->toThrow($excepcion)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe($aplicaciones)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(0);
})->with(['sin_credito', 'sin_deuda', 'cargo_otra_edicion', 'cargo_otro_equipo', 'cargo_inexistente', 'clave_invalida', 'credito_corrupto', 'deuda_corrupta', 'padre_borrado', 'padre_foraneo', 'config_ausente', 'config_inactiva', 'actor_revocado', 'actor_borrado']);

it('usa el admin vigente leido fresco de la base y no el objeto actor en memoria', function (): void {
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);
    $this->admin->rol = 'regular';
    $this->admin->name = 'Nombre Viejo';
    $destino = crearCargoCredito($this->contexto, $this->equipo, 5000);

    $evento = $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, $this->clave);

    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->actor_nombre)->toBe('Nombre Vigente');
});

it('revierte atomicamente si un hook creating veta la aplicacion o el historial', function (string $clase): void {
    $dispatcherOriginal = Model::getEventDispatcher();
    Model::setEventDispatcher(clone $dispatcherOriginal);
    try {
        $clase::creating(fn (): bool => false);
        $destino = crearCargoCredito($this->contexto, $this->equipo, 5000);

        expect(fn () => $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, $this->clave))
            ->toThrow(RuntimeException::class)
            ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
            ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(0);
    } finally {
        Model::setEventDispatcher($dispatcherOriginal);
    }
})->with(['aplicacion' => AplicacionPagoPlanilla::class, 'historial' => HistorialCobroPlanilla::class]);

it('lee con lock actual el pago los cargos y las aplicaciones dentro de la operacion', function (): void {
    $consultas = [];
    $conexion = DB::connection();
    $dispatcher = $conexion->getEventDispatcher();
    $conexion->setEventDispatcher(clone $dispatcher);
    try {
        $conexion->listen(function (QueryExecuted $consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });
        $destino = crearCargoCredito($this->contexto, $this->equipo, 5000);
        $this->servicio->aplicarCredito($this->admin, $this->pago->id, $destino->id, $this->clave);
    } finally {
        $conexion->setEventDispatcher($dispatcher);
    }
    $bloqueos = collect($consultas)->filter(fn (string $sql): bool => str_contains($sql, 'for update'))->implode(' ');
    expect($bloqueos)->toContain('`pago_planillas`')->toContain('`cargo_planillas`')->toContain('`aplicacion_pago_planillas`');
});
