<?php

// Regresión de la edición de un pago de planilla: las diecisiete sondas de la
// verificación independiente quedan como pruebas permanentes. Cubren el reparto
// del excedente, los bloqueos e idempotencia, el 3–0 derivado, la exención previa,
// el polling, el N+1, el motivo vacío, la edición sin cambios y los accesos.

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Equipo;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\EdicionPagosCobroService;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

function regresionCargoEditarPago(array $contexto, Equipo $equipo, string $horario, int $precio = 25000, ?Equipo $rival = null): CargoPlanilla
{
    $rival ??= crearEquipoCargos($contexto, 'Rival '.$horario.Str::random(4));
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio, 'snapshot' => [],
    ]);
}

/** Fotografía de las tablas financieras para probar que un rechazo no escribe nada. */
function regresionFotoEditarPago(): string
{
    return json_encode(collect(['pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas', 'partidos'])
        ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->orderBy('id')->get()->all()])->all());
}

function regresionAplicadoEditarPago(int $cargoId): int
{
    return (int) AplicacionPagoPlanilla::query()->where('cargo_id', $cargoId)->sum('importe_ars');
}

function regresionEntradaEditarPago(CargoPlanilla $cargo, PagoPlanilla $pago, array $cambios = []): array
{
    return array_replace([
        'idEdicion' => $cargo->idEdicion, 'idEquipo' => $cargo->idEquipo, 'cargo_id' => $cargo->id,
        'pago_id' => $pago->id, 'importe_ars' => '4000', 'medio' => 'efectivo',
        'clave_idempotencia' => (string) Str::uuid(),
    ], $cambios);
}

function regresionFirmaPollingEditarPago(string $html): string
{
    preg_match('/data-firma="([^"]+)"/', $html, $firma);

    return $firma[1] ?? '';
}

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->contexto = crearContextoCargos('Regresión editar pago');
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo regresión');
    $this->cargo = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-05 10:00:00');
    $this->admin = crearAdminCargos('Admin regresión');
    activarCobrosCargos();
    $this->servicio = new EdicionPagosCobroService;
    $this->consulta = app(ConsultaCobrosService::class);
});

it('sube el importe sobre la deuda y reparte el excedente FIFO por fecha vigente', function (): void {
    $tarde = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-08 10:00:00');
    $temprano = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-06 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $clave = (string) Str::uuid();

    $evento = $this->servicio->editarPago($this->admin, $pago->id, 45000, 'efectivo', null, $clave);

    expect([regresionAplicadoEditarPago($this->cargo->id), regresionAplicadoEditarPago($temprano->id), regresionAplicadoEditarPago($tarde->id)])
        ->toBe([25000, 20000, 0])
        ->and((int) AplicacionPagoPlanilla::query()->where('pago_id', $pago->id)->sum('importe_ars'))->toBe(45000)
        ->and([$this->consulta->deudaCargo($this->cargo->id), $this->consulta->deudaCargo($temprano->id), $this->consulta->deudaCargo($tarde->id)])
        ->toBe([0, 5000, 25000])
        ->and(collect($evento->valores_despues['aplicaciones'])->map(fn (array $fila): array => [$fila['cargo_id'], $fila['importe_ars']])->all())
        ->toBe([[$this->cargo->id, 25000], [$temprano->id, 20000]])
        ->and($evento->valores_despues['clave_credito'])->not->toBe(strtolower($clave))
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1);

    // El excedente ya cubrió otra planilla: la edición queda bloqueada.
    expect(fn () => $this->servicio->editarPago($this->admin, $pago->id, 9000, 'efectivo', null, (string) Str::uuid()))
        ->toThrow(ValidationException::class, 'la edición está bloqueada');
});

it('sube por encima de todas las deudas y conserva el resto como crédito del pago', function (): void {
    $otro = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-08 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());

    $this->servicio->editarPago($this->admin, $pago->id, 2147483647, 'mercado_pago', null, (string) Str::uuid());

    $vigente = $this->consulta->resultadoPago(PagoPlanilla::query()->with('cuenta')->find($pago->id));
    expect([regresionAplicadoEditarPago($this->cargo->id), regresionAplicadoEditarPago($otro->id)])->toBe([25000, 25000])
        ->and($vigente['credito_pago_ars'])->toBe(2147483647 - 50000)
        ->and($vigente['medio'])->toBe('mercado_pago');
});

it('sube sin otras deudas y conserva el excedente como crédito sin distribuir', function (): void {
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());

    $evento = $this->servicio->editarPago($this->admin, $pago->id, 30000, 'efectivo', null, (string) Str::uuid());

    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(25000)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(0)
        ->and(array_key_exists('clave_credito', $evento->valores_despues))->toBeFalse();

    // Sigue editable porque solo tiene su aplicación de origen; al bajar vuelve a deber.
    $this->servicio->editarPago($this->admin, $pago->id, 1, 'efectivo', null, (string) Str::uuid());

    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(1)
        ->and($this->consulta->deudaCargo($this->cargo->id))->toBe(24999);
});

it('edita por separado cada pago de un cobro combinado y el reintento del registro no duplica', function (): void {
    $otro = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-06 10:00:00');
    $this->actingAs($this->admin);
    $registro = ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'cargo_id' => $this->cargo->id,
        'importe_efectivo_ars' => '10000', 'importe_mercado_pago_ars' => '5000', 'clave_idempotencia' => (string) Str::uuid()];
    $this->postJson('/Panel/cobros/pagos', $registro)->assertSuccessful();
    $efectivo = PagoPlanilla::query()->where('medio', 'efectivo')->sole();
    $mercadoPago = PagoPlanilla::query()->where('medio', 'mercado_pago')->sole();

    $html = $this->get(route('cobros.partido', ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $this->cargo->parent_id, 'idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();
    expect(substr_count($html, 'data-cobro="editar_pago"'))->toBe(2);

    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $mercadoPago, ['importe_ars' => '15000', 'medio' => 'mercado_pago']))
        ->assertSuccessful()->assertJsonPath('pago.importe_ars', 15000)->assertJsonPath('pago.deuda_cargo_ars', 0);
    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(25000);
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $efectivo, ['importe_ars' => '12000']))
        ->assertSuccessful()->assertJsonPath('pago.aplicado_origen_ars', 10000)->assertJsonPath('pago.aplicado_ars', 12000);
    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(25000)
        ->and(regresionAplicadoEditarPago($otro->id))->toBe(2000);

    $foto = regresionFotoEditarPago();
    $this->postJson('/Panel/cobros/pagos', $registro)->assertSuccessful();
    expect(regresionFotoEditarPago())->toBe($foto)->and(PagoPlanilla::query()->count())->toBe(2);
});

it('rechaza identificadores manipulados de pago cargo equipo y edición sin escribir', function (): void {
    $otroEquipo = crearEquipoCargos($this->contexto, 'Otro equipo regresión');
    $cargoOtroEquipo = regresionCargoEditarPago($this->contexto, $otroEquipo, '2026-04-07 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $pagoOtroEquipo = (new PagosCobroService)->registrarPago($this->admin, $cargoOtroEquipo->id, 8000, 'efectivo', (string) Str::uuid());
    $foto = regresionFotoEditarPago();
    $this->actingAs($this->admin);

    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pagoOtroEquipo))->assertUnprocessable()->assertJsonValidationErrors('pago_id');
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($cargoOtroEquipo, $pago))->assertUnprocessable()->assertJsonValidationErrors('pago_id');
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['cargo_id' => $cargoOtroEquipo->id]))->assertUnprocessable()->assertJsonValidationErrors('cargo_id');
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['pago_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('pago_id');
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['pago_id' => 'abc']))->assertUnprocessable();
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '-5']))->assertUnprocessable();
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '1e3']))->assertUnprocessable();

    expect(regresionFotoEditarPago())->toBe($foto);
});

it('resuelve el reintento de la edición y conserva la identidad y la clave del registro original', function (): void {
    $claveRegistro = (string) Str::uuid();
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $claveRegistro);
    $clave = (string) Str::uuid();
    $primera = $this->servicio->editarPago($this->admin, $pago->id, 4000, 'mercado_pago', 'm1', $clave);
    $foto = regresionFotoEditarPago();

    expect($this->servicio->editarPago($this->admin, $pago->id, 4000, 'mercado_pago', 'm1', strtoupper($clave))->id)->toBe($primera->id)
        ->and(regresionFotoEditarPago())->toBe($foto);

    foreach ([[4000, 'mercado_pago', 'otro'], [4000, 'efectivo', 'm1'], [4001, 'mercado_pago', 'm1'], [4000, 'mercado_pago', null]] as [$importe, $medio, $motivo]) {
        expect(fn () => $this->servicio->editarPago($this->admin, $pago->id, $importe, $medio, $motivo, $clave))->toThrow(ValidationException::class);
    }
    $otroAdmin = crearAdminCargos('Otro admin regresión');
    expect(fn () => $this->servicio->editarPago($otroAdmin, $pago->id, 4000, 'mercado_pago', 'm1', $clave))->toThrow(ValidationException::class);
    expect(regresionFotoEditarPago())->toBe($foto);

    // Una segunda edición por otro administrador no cambia la identidad del recibo.
    $segunda = $this->servicio->editarPago($otroAdmin, $pago->id, 7000, 'efectivo', null, (string) Str::uuid());
    $vigente = PagoPlanilla::query()->find($pago->id);
    expect([$vigente->usuario_id, $vigente->actor_nombre, $vigente->clave_idempotencia])
        ->toBe([$this->admin->id, 'Admin regresión', strtolower($claveRegistro)])
        ->and($segunda->usuario_id)->toBe($otroAdmin->id)
        ->and($segunda->valores_antes['importe_ars'])->toBe(4000)
        ->and($segunda->valores_antes['medio'])->toBe('mercado_pago')
        ->and($segunda->valores_antes['aplicaciones'][0]['importe_ars'])->toBe(4000);

    $fotoPosterior = regresionFotoEditarPago();
    $reintento = $this->servicio->editarPago($this->admin, $pago->id, 4000, 'mercado_pago', 'm1', $clave);
    expect($reintento->id)->toBe($primera->id)
        ->and($reintento->valores_despues['importe_ars'])->toBe(4000)
        ->and(regresionFotoEditarPago())->toBe($fotoPosterior)
        ->and((int) PagoPlanilla::query()->find($pago->id)->importe_ars)->toBe(7000);

    // El reintento del registro original sigue devolviendo el mismo pago tras dos ediciones.
    expect((new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', $claveRegistro)->id)->toBe($pago->id)
        ->and(regresionFotoEditarPago())->toBe($fotoPosterior);
    expect(fn () => (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 7000, 'efectivo', $claveRegistro))
        ->toThrow(ValidationException::class);
});

it('no reutiliza la clave de una distribución de crédito ajena al editar el pago', function (): void {
    $b = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-08 10:00:00');
    $c = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-09 10:00:00');
    $this->actingAs($this->admin);
    $claveRegistro = (string) Str::uuid();
    // Registro HTTP con excedente: distribuye crédito con la clave del registro.
    $this->postJson('/Panel/cobros/pagos', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'cargo_id' => $b->id,
        'importe_ars' => '30000', 'medio' => 'efectivo', 'clave_idempotencia' => $claveRegistro])->assertSuccessful();
    expect(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(1)
        ->and(regresionAplicadoEditarPago($this->cargo->id))->toBe(5000);

    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->servicio->editarPago($this->admin, $pago->id, 25000, 'efectivo', null, $claveRegistro);

    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(25000)
        ->and(regresionAplicadoEditarPago($c->id))->toBe(5000)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(2);
});

it('suspende y vuelve a aplicar el 3 a 0 automático según la deuda del ganador', function (): void {
    $local = crearEquipoCargos($this->contexto, 'Local regresión');
    $visitante = crearEquipoCargos($this->contexto, 'Visitante regresión');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $local, $visitante));
    $crearCargo = fn (Equipo $equipo): CargoPlanilla => CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    $cargoLocal = $crearCargo($local);
    $cargoVisitante = $crearCargo($visitante);
    $pago = (new PagosCobroService)->registrarPago($this->admin, $cargoVisitante->id, 25000, 'efectivo', (string) Str::uuid());
    app(AusenciasCobroService::class)->marcarNoVino($this->admin, $local->id, $cargoLocal->id);
    expect([$partido->fresh()->golesEquipoLocal, $partido->fresh()->golesEquipoVisitante])->toBe([0, 3]);

    $this->servicio->editarPago($this->admin, $pago->id, 24999, 'efectivo', null, (string) Str::uuid());
    expect([$partido->fresh()->golesEquipoLocal, $partido->fresh()->golesEquipoVisitante])->toBe([null, null]);

    // Al volver a cubrir la planilla del ganador, la regla existente reaplica el 3 a 0.
    $this->servicio->editarPago($this->admin, $pago->id, 25000, 'mercado_pago', null, (string) Str::uuid());
    $partido = $partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([0, 3, 1]);
});

it('revierte toda la edición si la sincronización del 3 a 0 falla después de escribir', function (): void {
    regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-08 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $foto = regresionFotoEditarPago();
    $falla = Mockery::mock(AusenciasCobroService::class);
    $falla->shouldReceive('sincronizarResultadoDeCargo')->andThrow(new RuntimeException('boom'));
    app()->instance(AusenciasCobroService::class, $falla);

    expect(fn () => $this->servicio->editarPago($this->admin, $pago->id, 40000, 'mercado_pago', null, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
    expect(regresionFotoEditarPago())->toBe($foto);
});

it('respeta la exención previa al bajar y volver a subir el importe', function (): void {
    $otro = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-06 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id, 'x');
    expect($this->consulta->deudaCargo($this->cargo->id))->toBe(0);

    $this->servicio->editarPago($this->admin, $pago->id, 4000, 'efectivo', null, (string) Str::uuid());
    expect($this->consulta->deudaCargo($this->cargo->id))->toBe(6000);

    $this->servicio->editarPago($this->admin, $pago->id, 12000, 'efectivo', null, (string) Str::uuid());
    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(10000)
        ->and(regresionAplicadoEditarPago($otro->id))->toBe(2000);
});

it('calcula la deuda del cargo de origen sin contar el pago editado cuando hay dos parciales', function (): void {
    $otro = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-06 10:00:00');
    $primero = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $segundo = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 15000, 'efectivo', (string) Str::uuid());

    $this->servicio->editarPago($this->admin, $primero->id, 20000, 'efectivo', null, (string) Str::uuid());
    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(25000)
        ->and(regresionAplicadoEditarPago($otro->id))->toBe(10000);

    $this->servicio->editarPago($this->admin, $segundo->id, 5000, 'efectivo', null, (string) Str::uuid());
    expect(regresionAplicadoEditarPago($this->cargo->id))->toBe(15000)
        ->and($this->consulta->deudaCargo($this->cargo->id))->toBe(10000);
});

it('cambia la firma del polling del partido y de la franja al editar solo el medio', function (): void {
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->actingAs($this->admin);
    $partido = Partido::query()->find($this->cargo->parent_id);
    $urlPartido = route('cobros.partido', ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $partido->id, 'idEdicion' => $this->contexto['edicion']->id]);
    $urlFranja = route('planilla.deudas', ['partidoId' => $partido->id, 'idEdicion' => $this->contexto['edicion']->id, 'tipoPartido' => 'partido']);
    $firma = fn (string $url): string => regresionFirmaPollingEditarPago($this->get($url)->assertOk()->getContent());
    [$firmaPartidoAntes, $firmaFranjaAntes] = [$firma($urlPartido), $firma($urlFranja)];
    expect($firmaPartidoAntes)->not->toBe('')->and($firmaFranjaAntes)->not->toBe('');

    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '10000', 'medio' => 'mercado_pago']))
        ->assertSuccessful();

    expect($firma($urlPartido))->not->toBe($firmaPartidoAntes)
        ->and($firma($urlFranja))->not->toBe($firmaFranjaAntes);
});

it('mantiene constante la cantidad de consultas de la deuda con uno u ocho pagos', function (): void {
    $consultas = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->consulta->deudasParaPlanilla($this->contexto['edicion']->id, [$this->equipo->id], '2026-12-31 10:00:00');
        $cantidad = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $cantidad;
    };
    $cargos = collect(range(6, 13))->map(fn (int $dia): CargoPlanilla => regresionCargoEditarPago($this->contexto, $this->equipo, sprintf('2026-04-%02d 10:00:00', $dia)));
    (new PagosCobroService)->registrarPago($this->admin, $cargos[0]->id, 1000, 'efectivo', (string) Str::uuid());
    $conUnPago = $consultas();
    foreach ($cargos->slice(1) as $cargo) {
        (new PagosCobroService)->registrarPago($this->admin, $cargo->id, 1000, 'efectivo', (string) Str::uuid());
    }

    expect($consultas())->toBe($conUnPago);
});

it('guarda sin motivo cuando el motivo está vacío o solo tiene espacios y el detalle omite esa línea', function (string $motivo): void {
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->actingAs($this->admin);

    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '4000', 'motivo' => $motivo]))
        ->assertSuccessful()->assertJsonPath('evento.motivo', null);

    expect(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->sole()->valores_despues['motivo'])->toBeNull();
    $html = $this->get(route('cobros.detalle', ['idEdicion' => $this->cargo->idEdicion, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->getContent();
    $historial = Str::between($html, '<section aria-label="Historial auditable">', '</section>');
    expect($historial)->toContain('editado:')->not->toContain('Motivo:');
})->with(['sin texto' => '', 'solo espacios' => '   ']);

it('rechaza la edición sin cambios sin escribir y falla cerrado con los cobros desactivados', function (): void {
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->actingAs($this->admin);
    $foto = regresionFotoEditarPago();

    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '10000', 'medio' => 'efectivo']))
        ->assertUnprocessable()->assertJsonPath('errors.importe_ars.0', 'No hay cambios para guardar.');
    expect(regresionFotoEditarPago())->toBe($foto)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'editar_pago')->count())->toBe(0);

    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    $this->postJson(route('cobros.pagos-editar'), regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '5000']))
        ->assertStatus(500)->assertJsonPath('message', 'No se pudo completar la operación. Revisá e intentá nuevamente.');
    expect(regresionFotoEditarPago())->toBe($foto);
});

it('no muestra la acción de editar pago a un editor y sí al administrador', function (): void {
    (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $url = route('planilla.show', ['partidoId' => $this->cargo->parent_id, 'idEdicion' => $this->contexto['edicion']->id, 'tipoPartido' => 'partido', 'horario' => 0]);

    $this->actingAs(crearUsuarioConRolCargos('editor'))->get($url)->assertOk()
        ->assertDontSee('data-cobro="editar_pago"', false)->assertDontSee('Editar pago');
    $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('data-cobro="editar_pago"', false);
});

it('devuelve el evento original de una edición que repartió crédito aunque el pago ya esté bloqueado', function (): void {
    $otro = regresionCargoEditarPago($this->contexto, $this->equipo, '2026-04-08 10:00:00');
    $pago = (new PagosCobroService)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->actingAs($this->admin);
    $entrada = regresionEntradaEditarPago($this->cargo, $pago, ['importe_ars' => '30000']);
    $primera = $this->postJson(route('cobros.pagos-editar'), $entrada)->assertSuccessful()->json();
    $foto = regresionFotoEditarPago();

    $this->postJson(route('cobros.pagos-editar'), $entrada)->assertSuccessful()
        ->assertJsonPath('evento.id', $primera['evento']['id'])->assertJsonPath('pago.importe_ars', 30000);

    expect(regresionFotoEditarPago())->toBe($foto)->and(regresionAplicadoEditarPago($otro->id))->toBe(5000);
    $html = $this->get(route('cobros.partido', ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $this->cargo->parent_id, 'idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();
    expect($html)->toContain('data-pago-registrado="'.$pago->id.'" data-editable="0"')
        ->and($html)->not->toContain('data-cobro="editar_pago"');
});
