<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Services\Cobros\AnulacionCargosService;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use App\Services\Cobros\ProgramacionCobroService;
use App\Services\PartidoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

/**
 * Al eliminar un partido con cargos de planilla, la deuda no debe quedar
 * huérfana: sin pagos se anula con una exención; con pagos, se bloquea.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Tacuari Eliminacion');
    $this->visitante = crearEquipoCargos($this->contexto, 'Peques Eliminacion');
    $this->admin = crearAdminCargos();
    activarCobrosCargos();

    $this->crearPartido = fn (): Partido => app(ProgramacionCobroService::class)
        ->crearPartido($this->admin, crearDatosPartidoCargos($this->contexto, $this->local, $this->visitante));

    $this->eliminar = fn (string $ruta, int $id) => $this->actingAs($this->admin)
        ->withSession(['_token' => 'token-csrf-eliminacion'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-eliminacion'])
        ->delete(route($ruta, [$ruta === 'partido.destroy' ? 'partido' : 'instancia_final' => $id, 'idEdicion' => $this->contexto['edicion']->id]));

    $this->deudasDelLocal = fn () => app(ConsultaCobrosService::class)
        ->deudasParaPlanilla($this->contexto['edicion']->id, [$this->local->id], '2026-04-05 10:00:00')['deudas']
        ->where('idEquipo', $this->local->id);
});

it('anula los cargos sin pagos del partido duplicado eliminado y el equipo debe una sola planilla', function (): void {
    $original = ($this->crearPartido)();
    $duplicado = ($this->crearPartido)();
    expect(($this->deudasDelLocal)())->toHaveCount(2);

    ($this->eliminar)('partido.destroy', $duplicado->id)
        ->assertSessionHas('status', 'Partido eliminado con éxito.');

    $this->assertDatabaseMissing('partidos', ['id' => $duplicado->id]);
    $cargosDuplicado = CargoPlanilla::query()->where('parent_type', Partido::class)->where('parent_id', $duplicado->id)->get();
    expect($cargosDuplicado)->toHaveCount(2);
    foreach ($cargosDuplicado as $cargo) {
        $exencion = ExencionCobroPlanilla::query()->where('cargo_id', $cargo->id)->sole();
        expect($exencion->importe_ars)->toBe(25000)
            ->and($exencion->motivo)->toBe('Partido eliminado')
            ->and($exencion->usuario_id)->toBe($this->admin->id);
    }

    $deudas = ($this->deudasDelLocal)();
    expect($deudas)->toHaveCount(1)
        ->and($deudas->first()['id'])->toBe(CargoPlanilla::query()->where('parent_id', $original->id)->where('idEquipo', $this->local->id)->value('id'));

    $evento = HistorialCobroPlanilla::query()->where('accion', 'anular_cargos_partido_eliminado')->sole();
    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->valores_antes['parent'])->toEqual(['tipo' => Partido::class, 'id' => $duplicado->id])
        ->and($evento->valores_despues['cargos'])->toBe($cargosDuplicado->pluck('id')->all())
        ->and($evento->valores_despues['anulado_ars'])->toBe(50000);
});

it('bloquea la eliminacion del partido cuando un equipo ya pago su planilla', function (): void {
    $partido = ($this->crearPartido)();
    $cargo = CargoPlanilla::query()->where('parent_id', $partido->id)->where('idEquipo', $this->local->id)->sole();
    app(PagosCobroService::class)->registrarPago($this->admin, $cargo->id, 10000, 'efectivo', (string) Str::uuid());

    ($this->eliminar)('partido.destroy', $partido->id)
        ->assertSessionHas('error', 'No se puede eliminar el partido: ya tiene pagos de planilla registrados.');

    $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
    expect(ExencionCobroPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'anular_cargos_partido_eliminado')->exists())->toBeFalse();
});

it('no vuelve a eximir un cargo ya eximido al eliminar su partido', function (): void {
    $partido = ($this->crearPartido)();
    $cargo = CargoPlanilla::query()->where('parent_id', $partido->id)->where('idEquipo', $this->local->id)->sole();
    app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->local->id, $cargo->id, 'Acuerdo');

    ($this->eliminar)('partido.destroy', $partido->id)
        ->assertSessionHas('status', 'Partido eliminado con éxito.');

    expect(ExencionCobroPlanilla::query()->where('cargo_id', $cargo->id)->sole()->motivo)->toBe('Acuerdo')
        ->and(ExencionCobroPlanilla::query()->where('motivo', 'Partido eliminado')->count())->toBe(1);
});

it('elimina como antes un partido sin cargos de planilla', function (): void {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    $partido = ($this->crearPartido)();

    ($this->eliminar)('partido.destroy', $partido->id)
        ->assertSessionHas('status', 'Partido eliminado con éxito.');

    $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);
    expect(HistorialCobroPlanilla::query()->where('accion', 'anular_cargos_partido_eliminado')->exists())->toBeFalse();
});

it('anula el cargo con no vino para que deshacer la ausencia no devuelva la deuda', function (): void {
    $partido = ($this->crearPartido)();
    $cargo = CargoPlanilla::query()->where('parent_id', $partido->id)->where('idEquipo', $this->local->id)->sole();
    app(AusenciasCobroService::class)->marcarNoVino($this->admin, $this->local->id, $cargo->id);

    ($this->eliminar)('partido.destroy', $partido->id)
        ->assertSessionHas('status', 'Partido eliminado con éxito.');

    expect(ExencionCobroPlanilla::query()->where('cargo_id', $cargo->id)->sole()->importe_ars)->toBe(25000);
    app(AusenciasCobroService::class)->deshacerNoVino($this->admin, $this->local->id, $cargo->id);
    expect(($this->deudasDelLocal)())->toHaveCount(0);
});

it('bloquea la eliminacion cuando la planilla se cubrio con credito de otro pago', function (): void {
    $pagado = ($this->crearPartido)();
    $conCredito = ($this->crearPartido)();
    $cargoPagado = CargoPlanilla::query()->where('parent_id', $pagado->id)->where('idEquipo', $this->local->id)->sole();
    $cargoConCredito = CargoPlanilla::query()->where('parent_id', $conCredito->id)->where('idEquipo', $this->local->id)->sole();
    $pago = app(PagosCobroService::class)->registrarPago($this->admin, $cargoPagado->id, 50000, 'efectivo', (string) Str::uuid());
    app(CreditosCobroService::class)->aplicarCredito($this->admin, $pago->id, $cargoConCredito->id, (string) Str::uuid());

    ($this->eliminar)('partido.destroy', $conCredito->id)
        ->assertSessionHas('error', AnulacionCargosService::MENSAJE_CON_PAGOS);

    $this->assertDatabaseHas('partidos', ['id' => $conCredito->id]);
    expect(ExencionCobroPlanilla::query()->count())->toBe(0);
});

it('rechaza al actor sin rol admin sin escribir nada', function (): void {
    $partido = ($this->crearPartido)();
    $editor = crearUsuarioConRolCargos('editor');

    expect(fn () => app(PartidoService::class)->eliminarPartido($partido, $editor))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('partidos', ['id' => $partido->id]);
    expect(ExencionCobroPlanilla::query()->count())->toBe(0);
});

it('avisa con un mensaje si falla la eliminacion de una instancia final', function (): void {
    $final = app(ProgramacionCobroService::class)
        ->crearInstanciaFinal($this->admin, crearDatosFinalCargos($this->contexto, $this->local, $this->visitante));
    InstanciaFinal::deleting(function (): void {
        throw new RuntimeException('Fallo simulado al eliminar la final');
    });

    ($this->eliminar)('instancia_final.destroy', $final->id)
        ->assertRedirect()
        ->assertSessionHas('error', 'No se pudo eliminar el partido. Intenta nuevamente.');

    $this->assertDatabaseHas('instancias_finales', ['id' => $final->id]);
    expect(ExencionCobroPlanilla::query()->count())->toBe(0);
});

it('anula los cargos de una instancia final eliminada y bloquea si tiene pagos', function (bool $conPago): void {
    $final = app(ProgramacionCobroService::class)
        ->crearInstanciaFinal($this->admin, crearDatosFinalCargos($this->contexto, $this->local, $this->visitante));
    $cargos = CargoPlanilla::query()->where('parent_type', InstanciaFinal::class)->where('parent_id', $final->id)->get();
    if ($conPago) {
        app(PagosCobroService::class)->registrarPago($this->admin, $cargos->first()->id, 25000, 'efectivo', (string) Str::uuid());
    }

    $respuesta = ($this->eliminar)('instancia_final.destroy', $final->id);

    if ($conPago) {
        $respuesta->assertSessionHas('error', 'No se puede eliminar el partido: ya tiene pagos de planilla registrados.');
        $this->assertDatabaseHas('instancias_finales', ['id' => $final->id]);
        expect(ExencionCobroPlanilla::query()->count())->toBe(0);
    } else {
        $respuesta->assertSessionHas('status', 'Partido eliminado con éxito.');
        $this->assertDatabaseMissing('instancias_finales', ['id' => $final->id]);
        expect(ExencionCobroPlanilla::query()->whereIn('cargo_id', $cargos->pluck('id'))->where('motivo', 'Partido eliminado')->count())->toBe(2);
    }
})->with(['sin pago' => false, 'con pago' => true]);
