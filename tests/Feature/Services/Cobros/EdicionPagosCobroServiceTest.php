<?php

// Corregir importe y medio de un pago ya registrado: se modifica el mismo recibo
// bajo los mismos bloqueos del registro, se recalcula su aplicación de origen y
// el excedente se distribuye como crédito, con auditoría e idempotencia.
// El resultado público es el evento append-only `editar_pago`: su snapshot no
// cambia aunque el pago se vuelva a editar después.

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\EdicionPagosCobroService;
use App\Services\Cobros\PagosCobroService;
use App\Services\PartidoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

function crearCargoEdicion(array $contexto, $equipo, int $precio = 25000, string $horario = '2026-04-05 10:00:00'): CargoPlanilla
{
    $rival = crearEquipoCargos($contexto, 'Rival '.$horario);
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));

    return cargoEdicionDePartido($partido, $equipo, $contexto, $precio);
}

function cargoEdicionDePartido(Partido $partido, $equipo, array $contexto, int $precio = 25000): CargoPlanilla
{
    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio, 'snapshot' => [],
    ]);
}

/** Fotografía de las tablas financieras para probar que un rechazo no escribe nada. */
function estadoEdicionCobros(): array
{
    return collect(['pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'])
        ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->orderBy('id')->get()->all()])->all();
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Edición');
    $this->cargo = crearCargoEdicion($this->contexto, $this->equipo);
    $this->admin = crearAdminCargos('Operador Edición');
    activarCobrosCargos();
    $this->claveRegistro = 'a1111111-1111-4111-8111-111111111111';
    $this->pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->claveRegistro);
    $this->servicio = new EdicionPagosCobroService;
    $this->clave = 'b2222222-2222-4222-8222-222222222222';
    $this->claveB = 'b3333333-3333-4333-8333-333333333333';
});

it('edita solo el medio y conserva identidad recepción clave y aplicación', function (): void {
    $original = $this->pago->fresh();
    $aplicacion = AplicacionPagoPlanilla::query()->sole();

    $evento = $this->servicio->editarPago($this->admin, $original->id, 10000, 'mercado_pago', null, $this->clave);
    $vigente = PagoPlanilla::query()->findOrFail($original->id);

    expect($evento->accion)->toBe('editar_pago')
        ->and($vigente->id)->toBe($original->id)
        ->and([$vigente->importe_ars, $vigente->medio])->toBe([10000, 'mercado_pago'])
        ->and($vigente->recibido_at)->toEqual($original->recibido_at)
        ->and([$vigente->usuario_id, $vigente->cuenta_id, $vigente->cargo_origen_id, $vigente->clave_idempotencia])
        ->toBe([$original->usuario_id, $original->cuenta_id, $original->cargo_origen_id, $original->clave_idempotencia])
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(AplicacionPagoPlanilla::query()->sole()->only(['id', 'importe_ars']))->toBe(['id' => $aplicacion->id, 'importe_ars' => 10000]);

    expect([$evento->usuario_id, $evento->actor_nombre])->toBe([$this->admin->id, 'Operador Edición'])
        ->and($evento->valores_antes['importe_ars'])->toBe(10000)
        ->and($evento->valores_antes['medio'])->toBe('efectivo')
        ->and($evento->valores_antes['aplicaciones'])->toEqual([['aplicacion_id' => $aplicacion->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 10000]])
        ->and($evento->valores_despues['importe_ars'])->toBe(10000)
        ->and($evento->valores_despues['medio'])->toBe('mercado_pago')
        ->and($evento->valores_despues['motivo'])->toBeNull();
});

it('audita importes y aplicaciones previas antes de mutarlos a lo largo de dos ediciones', function (): void {
    $aplicacion = AplicacionPagoPlanilla::query()->sole();
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);
    $this->servicio->editarPago($this->admin, $this->pago->id, 6000, 'efectivo', null, $this->claveB);
    $eventos = HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->orderBy('id')->get();
    [$primera, $segunda] = [$eventos[0], $eventos[1]];
    $fila = fn (int $importe): array => ['aplicacion_id' => $aplicacion->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => $importe];

    expect($primera->valores_antes['importe_ars'])->toBe(10000)
        ->and($primera->valores_antes['aplicaciones'])->toEqual([$fila(10000)])
        ->and($primera->valores_despues['aplicaciones'])->toEqual([$fila(4000)])
        ->and($segunda->valores_antes['importe_ars'])->toBe(4000)
        ->and($segunda->valores_antes['aplicaciones'])->toEqual([$fila(4000)])
        ->and($segunda->valores_despues['aplicaciones'])->toEqual([$fila(6000)])
        ->and(PagoPlanilla::query()->sole()->importe_ars)->toBe(6000);
});

it('baja el importe deja deuda parcial y guarda el motivo literal', function (): void {
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', 'Cobro mal cargado', $this->clave);

    expect(PagoPlanilla::query()->sole()->importe_ars)->toBe(4000)
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(4000)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1)
        ->and(app(ConsultaCobrosService::class)->deudaCargo($this->cargo->id))->toBe(21000)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->sole()->valores_despues['motivo'])->toBe('Cobro mal cargado');
});

it('sube el importe sin exceder la deuda del cargo de origen', function (): void {
    $this->servicio->editarPago($this->admin, $this->pago->id, 18000, 'efectivo', null, $this->clave);

    expect(AplicacionPagoPlanilla::query()->sole()->importe_ars)->toBe(18000)
        ->and(PagoPlanilla::query()->sole()->importe_ars)->toBe(18000)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(0)
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(18000);
});

it('sube el importe sobre la deuda y distribuye el excedente como crédito FIFO', function (): void {
    $temprano = crearCargoEdicion($this->contexto, $this->equipo, 3000, '2026-04-05 09:00:00');
    $tarde = crearCargoEdicion($this->contexto, $this->equipo, 25000, '2026-04-05 11:00:00');

    $evento = $this->servicio->editarPago($this->admin, $this->pago->id, 30000, 'mercado_pago', null, $this->clave);

    $porCargo = AplicacionPagoPlanilla::query()->selectRaw('cargo_id, SUM(importe_ars) as total')->groupBy('cargo_id')->pluck('total', 'cargo_id');
    expect((int) $porCargo[$this->cargo->id])->toBe(25000)
        ->and((int) $porCargo[$temprano->id])->toBe(3000)
        ->and((int) $porCargo[$tarde->id])->toBe(2000)
        ->and((int) AplicacionPagoPlanilla::query()->sum('importe_ars'))->toBe(30000)
        ->and(collect($evento->valores_despues['aplicaciones'])->pluck('cargo_id')->all())
        ->toBe([$this->cargo->id, $temprano->id, $tarde->id])
        ->and(collect($evento->valores_despues['aplicaciones'])->pluck('importe_ars')->all())
        ->toBe([25000, 3000, 2000]);
});

it('distribuye el excedente con una clave de crédito derivada distinta de la de edición', function (): void {
    crearCargoEdicion($this->contexto, $this->equipo, 25000, '2026-04-05 09:00:00');
    $esperada = EdicionPagosCobroService::claveDeCredito($this->clave, $this->pago->id);

    $evento = $this->servicio->editarPago($this->admin, $this->pago->id, 30000, 'efectivo', null, $this->clave);

    $credito = HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->sole();
    expect($esperada)->not->toBe($this->clave)
        ->and(strlen($esperada))->toBe(36)
        ->and($credito->valores_antes['clave_idempotencia'])->toBe($esperada)
        ->and($evento->valores_despues['clave_credito'] ?? null)->toBe($esperada)
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', '!=', $this->cargo->id)->sum('importe_ars'))->toBe(5000);

    $tablas = estadoEdicionCobros();
    $this->servicio->editarPago($this->admin, $this->pago->id, 30000, 'efectivo', null, $this->clave);
    expect(estadoEdicionCobros())->toEqual($tablas)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1);
});

it('rechaza la edición si la clave de crédito derivada ya fue usada por otra operación', function (): void {
    crearCargoEdicion($this->contexto, $this->equipo, 25000, '2026-04-05 09:00:00');
    $ajeno = crearCargoEdicion($this->contexto, $this->equipo, 25000, '2026-04-05 11:00:00');
    $pagoAjeno = (new PagosCobroService)->registrarPago($this->admin, $ajeno->id, 30000, 'efectivo', 'c8888888-8888-4888-8888-888888888888');
    $claveCredito = EdicionPagosCobroService::claveDeCredito($this->clave, $this->pago->id);
    (new CreditosCobroService)->aplicarCredito($this->admin, $pagoAjeno->id, $ajeno->id, $claveCredito);
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, 30000, 'efectivo', null, $this->clave))
        ->toThrow(RuntimeException::class, 'La clave de crédito derivada de la edición ya fue utilizada por otra operación.');
    expect(estadoEdicionCobros())->toEqual($antes);
});

it('bloquea la edición si el excedente ya cubrió otra planilla de la cuenta', function (): void {
    crearCargoEdicion($this->contexto, $this->equipo, 25000, '2026-04-06 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 30000, 'efectivo', 'c3333333-3333-4333-8333-333333333333');
    (new CreditosCobroService)->aplicarCredito($this->admin, $pago->id, $this->cargo->id, 'd4444444-4444-4444-8444-444444444444');
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $pago->id, 20000, 'efectivo', null, $this->clave))
        ->toThrow(ValidationException::class, 'El pago ya distribuyó su excedente como crédito a otras planillas: la edición está bloqueada.');
    expect(estadoEdicionCobros())->toEqual($antes);
});

it('rechaza actores que no son administradores vigentes sin escribir nada', function (string $caso): void {
    $actor = match ($caso) {
        'transitorio' => (new User)->forceFill(['id' => $this->admin->id, 'rol' => 'admin']),
        'regular' => crearUsuarioConRolCargos('regular'),
        'revocado' => tap($this->admin, fn () => User::query()->whereKey($this->admin->id)->update(['rol' => 'editor'])),
    };
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($actor, $this->pago->id, 4000, 'efectivo', null, $this->clave))
        ->toThrow(AuthorizationException::class);
    expect(estadoEdicionCobros())->toEqual($antes);
})->with(['transitorio', 'regular', 'revocado']);

it('falla cerrado sin configuración global activa y conserva el pago original', function (string $caso): void {
    $caso === 'ausente'
        ? ConfiguracionCobroPlanilla::query()->whereKey(1)->delete()
        : ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave))
        ->toThrow(RuntimeException::class);
    expect(estadoEdicionCobros())->toEqual($antes);
})->with(['ausente', 'inactiva']);

it('reintenta la misma clave con los mismos datos sin reaplicar ni reauditar', function (): void {
    $primero = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'mercado_pago', 'Motivo', $this->clave);
    $tablas = estadoEdicionCobros();
    $reintento = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'mercado_pago', 'Motivo', strtoupper($this->clave));

    expect($reintento->is($primero))->toBeTrue()
        ->and($reintento->valores_despues['importe_ars'])->toBe(4000)
        ->and(estadoEdicionCobros())->toEqual($tablas)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(1);
});

it('devuelve el evento inmutable de la edición sin presentar el estado vigente como resultado', function (): void {
    $primera = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);
    $segunda = $this->servicio->editarPago($this->admin, $this->pago->id, 6000, 'mercado_pago', 'Corrección posterior', $this->claveB);
    $tablas = estadoEdicionCobros();

    $replay = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);

    expect($segunda->id)->not->toBe($primera->id)
        ->and($replay->is($primera))->toBeTrue()
        ->and($replay->valores_antes['importe_ars'])->toBe(10000)
        ->and($replay->valores_despues['importe_ars'])->toBe(4000)
        ->and($replay->valores_despues['medio'])->toBe('efectivo')
        ->and($replay->valores_despues['motivo'])->toBeNull()
        ->and(PagoPlanilla::query()->sole()->only(['importe_ars', 'medio']))->toBe(['importe_ars' => 6000, 'medio' => 'mercado_pago'])
        ->and(estadoEdicionCobros())->toEqual($tablas);
});

it('rechaza la misma clave con otros datos sin reaplicar', function (string $caso): void {
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);
    $otroPago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 1000, 'efectivo', 'e5555555-5555-4555-8555-555555555555');
    $antes = estadoEdicionCobros();
    [$pagoId, $importe, $medio, $motivo] = match ($caso) {
        'pago' => [$otroPago->id, 4000, 'efectivo', null],
        'importe' => [$this->pago->id, 4001, 'efectivo', null],
        'medio' => [$this->pago->id, 4000, 'mercado_pago', null],
        'motivo' => [$this->pago->id, 4000, 'efectivo', 'Otro motivo'],
    };

    expect(fn () => $this->servicio->editarPago($this->admin, $pagoId, $importe, $medio, $motivo, $this->clave))
        ->toThrow(ValidationException::class);
    expect(estadoEdicionCobros())->toEqual($antes);
})->with(['pago', 'importe', 'medio', 'motivo']);

it('guarda sin motivo el texto vacío o solo con espacios y conserva literal el resto', function (): void {
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', "  \n  ", $this->clave);
    $this->servicio->editarPago($this->admin, $this->pago->id, 5000, 'efectivo', '  Texto literal  ', $this->claveB);
    $eventos = HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->orderBy('id')->get();

    expect($eventos[0]->valores_despues['motivo'])->toBeNull()
        ->and($eventos[1]->valores_despues['motivo'])->toBe('  Texto literal  ');
});

it('reintenta con motivo vacío la edición guardada sin motivo', function (): void {
    $primero = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);
    $tablas = estadoEdicionCobros();

    $reintento = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', '   ', $this->clave);

    expect($reintento->is($primero))->toBeTrue()
        ->and(estadoEdicionCobros())->toEqual($tablas)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(1);
});

it('rechaza la edición sin cambios de importe y medio sin escribir nada', function (): void {
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, 10000, 'efectivo', null, $this->clave))
        ->toThrow(ValidationException::class, 'No hay cambios para guardar.');
    expect(estadoEdicionCobros())->toEqual($antes)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(0);
});

it('devuelve el evento de una clave ya usada aunque sus valores coincidan con los vigentes', function (): void {
    $primera = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);
    $this->servicio->editarPago($this->admin, $this->pago->id, 6000, 'efectivo', null, $this->claveB);
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, 'c9999999-9999-4999-8999-999999999999');
    $tablas = estadoEdicionCobros();

    $replay = $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);

    expect($replay->is($primera))->toBeTrue()
        ->and(estadoEdicionCobros())->toEqual($tablas)
        ->and((int) PagoPlanilla::query()->findOrFail($this->pago->id)->importe_ars)->toBe(4000);
});

it('rechaza importes medios y claves fuera de contrato sin escribir nada', function (mixed $importe, string $medio, string $clave): void {
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, $importe, $medio, null, $clave))
        ->toThrow(ValidationException::class);
    expect(estadoEdicionCobros())->toEqual($antes);
})->with([
    'importe cero' => [0, 'efectivo', 'f6666666-6666-4666-8666-666666666666'],
    'importe decimal' => [1.5, 'efectivo', 'f6666666-6666-4666-8666-666666666666'],
    'importe texto' => ['4000', 'efectivo', 'f6666666-6666-4666-8666-666666666666'],
    'importe fuera de rango' => [2147483648, 'efectivo', 'f6666666-6666-4666-8666-666666666666'],
    'medio inválido' => [4000, 'cheque', 'f6666666-6666-4666-8666-666666666666'],
    'clave inválida' => [4000, 'efectivo', 'no-uuid'],
]);

it('rechaza un pago inexistente sin auditar', function (): void {
    expect(fn () => $this->servicio->editarPago($this->admin, 2147483647, 4000, 'efectivo', null, $this->clave))
        ->toThrow(ValidationException::class);
    expect(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(0);
});

it('rechaza la edición si la deuda del cargo de origen sin este pago ya es cero', function (): void {
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 25000]);
    $antes = estadoEdicionCobros();

    expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave))
        ->toThrow(ValidationException::class);
    expect(estadoEdicionCobros())->toEqual($antes);
});

it('suspende el 3 a 0 cuando la planilla del ganador deja de estar cubierta', function (bool $manual): void {
    $local = crearEquipoCargos($this->contexto, 'Local Edición');
    $visitante = crearEquipoCargos($this->contexto, 'Visitante Edición');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $local, $visitante));
    $cargoLocal = cargoEdicionDePartido($partido, $local, $this->contexto);
    $cargoVisitante = cargoEdicionDePartido($partido, $visitante, $this->contexto);
    $pago = (new PagosCobroService)->registrarPago($this->admin, $cargoVisitante->id, 25000, 'efectivo', 'a7777777-7777-4777-8777-777777777777');
    app(AusenciasCobroService::class)->marcarNoVino($this->admin, $local->id, $cargoLocal->id);
    expect([$partido->fresh()->golesEquipoLocal, $partido->fresh()->golesEquipoVisitante])->toBe([0, 3]);
    if ($manual) {
        app(PartidoService::class)->actualizarResultado($partido->fresh(), 1, 0);
    }

    $this->servicio->editarPago($this->admin, $pago->id, 10000, 'efectivo', null, $this->clave);

    $partido = $partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])
        ->toBe($manual ? [1, 0, 1] : [null, null, 0]);
})->with(['automatico' => false, 'manual' => true]);

it('no expone un camino público de edición y las escrituras ordinarias siguen bloqueadas', function (): void {
    $pago = PagoPlanilla::query()->findOrFail($this->pago->id);
    $aplicacion = AplicacionPagoPlanilla::query()->sole();

    expect(method_exists(PagoPlanilla::class, 'conEdicionPermitida'))->toBeFalse()
        ->and(method_exists(AplicacionPagoPlanilla::class, 'conEdicionPermitida'))->toBeFalse()
        ->and(fn () => $pago->update(['importe_ars' => 9000]))->toThrow(LogicException::class, 'Los recibos de pago no se pueden editar.')
        ->and(fn () => $pago->update(['medio' => 'mercado_pago']))->toThrow(LogicException::class)
        ->and(fn () => $pago->save())->toThrow(LogicException::class)
        ->and(fn () => $pago->delete())->toThrow(LogicException::class, 'Los recibos de pago se conservan para la historia financiera.')
        ->and(fn () => $aplicacion->update(['importe_ars' => 9000]))->toThrow(LogicException::class, 'Las aplicaciones de pagos no se pueden editar.')
        ->and(fn () => $aplicacion->save())->toThrow(LogicException::class)
        ->and(fn () => $aplicacion->delete())->toThrow(LogicException::class, 'Las aplicaciones se conservan para la historia financiera.');

    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave);

    expect(fn () => PagoPlanilla::query()->findOrFail($this->pago->id)->update(['medio' => 'mercado_pago']))->toThrow(LogicException::class)
        ->and(fn () => PagoPlanilla::query()->findOrFail($this->pago->id)->delete())->toThrow(LogicException::class)
        ->and(fn () => AplicacionPagoPlanilla::query()->sole()->update(['importe_ars' => 5000]))->toThrow(LogicException::class)
        ->and(PagoPlanilla::query()->sole()->only(['importe_ars', 'medio']))->toBe(['importe_ars' => 4000, 'medio' => 'efectivo']);
});

it('preserva la idempotencia del registro original después de editar el pago', function (): void {
    $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'mercado_pago', null, $this->clave);
    $tablas = estadoEdicionCobros();

    $replay = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $this->claveRegistro);

    expect($replay->is($this->pago))->toBeTrue()
        ->and($replay->clave_idempotencia)->toBe($this->claveRegistro)
        ->and(estadoEdicionCobros())->toEqual($tablas)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(1);

    expect(fn () => (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 9999, 'efectivo', $this->claveRegistro))
        ->toThrow(ValidationException::class)
        ->and(fn () => (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'mercado_pago', $this->claveRegistro))
        ->toThrow(ValidationException::class);
    expect(estadoEdicionCobros())->toEqual($tablas);
});

it('revierte atomicamente la edición si no se puede auditar', function (): void {
    $antes = estadoEdicionCobros();
    HistorialCobroPlanilla::creating(fn (): bool => false);

    try {
        expect(fn () => $this->servicio->editarPago($this->admin, $this->pago->id, 4000, 'efectivo', null, $this->clave))
            ->toThrow(RuntimeException::class);
        expect(estadoEdicionCobros())->toEqual($antes);
    } finally {
        HistorialCobroPlanilla::flushEventListeners();
        HistorialCobroPlanilla::clearBootedModels();
    }
});
