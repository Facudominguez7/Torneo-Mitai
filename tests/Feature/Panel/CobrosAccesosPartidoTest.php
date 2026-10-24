<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Local cobro');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante cobro');
    $this->partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->local, $this->visitante));
    $this->admin = crearAdminCargos();
    app(ConfiguracionCobroService::class)->actualizarTarifa($this->admin, 25000);
    app(ConfiguracionCobroService::class)->activar($this->admin);
    $this->cargos = [];
    foreach ([$this->local, $this->visitante] as $equipo) {
        $this->cargos[$equipo->id] = CargoPlanilla::create([
            'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $this->partido->id,
            'idEquipo' => $equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
            'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
            'snapshot' => ['equipo' => ['nombre' => $equipo->nombre]],
        ]);
    }
});

function aplicarPagoAccesos(CargoPlanilla $cargo, int $importe, int $usuarioId): void
{
    $cuenta = CuentaCobroPlanilla::create(['idEquipo' => $cargo->idEquipo, 'idEdicion' => $cargo->idEdicion]);
    $pago = PagoPlanilla::create([
        'cuenta_id' => $cuenta->id, 'cargo_origen_id' => $cargo->id, 'usuario_id' => $usuarioId,
        'importe_ars' => $importe, 'medio' => 'efectivo', 'clave_idempotencia' => (string) Str::uuid(),
    ]);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $cargo->id, 'importe_ars' => $importe]);
}

function rutaPlanillaAccesos(Partido $partido): string
{
    return route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $partido->idEdicion, 'tipoPartido' => 'partido', 'horario' => 0]);
}

it('muestra en el dashboard el estado de pago de cada equipo del partido', function (): void {
    aplicarPagoAccesos($this->cargos[$this->local->id], 25000, $this->admin->id);
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargos[$this->visitante->id]->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 25000]);

    $this->actingAs($this->admin)->get(route('admin', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('data-dashboard-cobro="'.$this->cargos[$this->local->id]->id.'"', false)
        ->assertSeeInOrder([$this->local->nombre.' (Local): Planilla pagada', $this->visitante->nombre.' (Visitante): Planilla exenta']);
});

it('distingue deuda total y parcial en el dashboard', function (): void {
    aplicarPagoAccesos($this->cargos[$this->visitante->id], 10000, $this->admin->id);

    $this->actingAs($this->admin)->get(route('admin', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSeeInOrder([$this->local->nombre.' (Local): Debe planilla $ 25.000', $this->visitante->nombre.' (Visitante): Pago parcial, debe $ 15.000']);
});

it('omite el estado de pago en el dashboard para partidos sin cargos', function (): void {
    $otro = Partido::create(crearDatosPartidoCargos($this->contexto, $this->visitante, $this->local));

    $respuesta = $this->actingAs($this->admin)->get(route('admin', ['idEdicion' => $this->contexto['edicion']->id]))->assertOk();
    $html = $respuesta->getContent();
    $tarjeta = Str::between($html, 'data-dashboard-partido="'.$otro->id.'"', 'data-dashboard-partido=');

    expect($tarjeta)->not->toContain('data-dashboard-cobro')
        ->and(substr_count($html, 'data-dashboard-cobro='))->toBe(2);
});

it('permite registrar el pago de cada equipo desde la planilla del partido', function (): void {
    aplicarPagoAccesos($this->cargos[$this->visitante->id], 25000, $this->admin->id);

    $respuesta = $this->actingAs($this->admin)->get(rutaPlanillaAccesos($this->partido))->assertOk();

    $respuesta->assertSee('id="cobros-modal"', false)
        ->assertSee('data-cobro="pago"', false)
        ->assertSeeInOrder(['Cobro de planilla', $this->local->nombre, 'Debe $ 25.000', 'Registrar pago', $this->visitante->nombre, 'Pagada']);
    $boton = Str::between($respuesta->getContent(), 'data-cobro="pago"', '>');
    expect(substr_count($respuesta->getContent(), 'data-cobro="pago"'))->toBe(1)
        ->and(html_entity_decode($boton))->toContain('"id":'.$this->cargos[$this->local->id]->id)
        ->and(html_entity_decode($boton))->toContain('"idEquipo":'.$this->local->id);
});

it('oculta el cobro de la planilla cuando el partido no tiene cargos', function (): void {
    $otro = Partido::create(crearDatosPartidoCargos($this->contexto, $this->visitante, $this->local));

    $this->actingAs($this->admin)->get(rutaPlanillaAccesos($otro))
        ->assertOk()
        ->assertDontSee('Cobro de planilla')
        ->assertDontSee('id="cobros-modal"', false);
});

it('imprime los cobros en la misma página sin abrir otra pestaña', function (): void {
    $html = $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();
    preg_match('/<a[^>]*data-cobros-imprimir[^>]*>/', $html, $coincidencia);
    $control = $coincidencia[0] ?? '';

    expect($control)->toContain('imprimirEnIframe(this.href)')
        ->and($control)->not->toContain('target="_blank"')
        ->and($control)->toContain(e(route('cobros.imprimir', ['idEdicion' => $this->contexto['edicion']->id])));
});

it('pide importes de efectivo y mercado pago sin ofrecer transferencia', function (): void {
    $html = $this->actingAs($this->admin)->get(rutaPlanillaAccesos($this->partido))->assertOk()->getContent();
    $modal = Str::between($html, 'id="cobros-modal"', '</dialog>');

    expect($modal)->toContain('name="importe_efectivo_ars"')
        ->and($modal)->toContain('name="importe_mercado_pago_ars"')
        ->and($modal)->not->toContain('name="importe_ars"')
        ->and($modal)->not->toContain('name="medio"')
        ->and(mb_strtolower($modal))->not->toContain('transferencia');
});

it('entrega el estado de cobro del partido para refrescar la planilla sin recargarla', function (): void {
    $parametros = ['idEdicion' => $this->contexto['edicion']->id, 'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $this->partido->id];
    $planilla = $this->actingAs($this->admin)->get(rutaPlanillaAccesos($this->partido))->assertOk();
    $planilla->assertSee('data-cobros-seccion', false)->assertSee(e(route('cobros.partido', $parametros)), false);

    $this->get(route('cobros.partido', $parametros))->assertOk()
        ->assertSee('data-deuda="25000"', false)->assertDontSee('id="cobros-modal"', false);

    aplicarPagoAccesos($this->cargos[$this->local->id], 25000, $this->admin->id);

    $this->get(route('cobros.partido', $parametros))->assertOk()
        ->assertSeeInOrder([$this->local->nombre, 'Pagada', $this->visitante->nombre, 'Debe $ 25.000'])
        ->assertSee('data-deuda="0"', false);
    expect(substr_count($this->get(route('cobros.partido', $parametros))->getContent(), 'data-cobro="pago"'))->toBe(1);
});

it('rechaza el estado de cobro de un partido ajeno a la edición o sin contexto', function (): void {
    $otra = crearContextoCargos('Otra edición');

    $this->actingAs($this->admin)->get(route('cobros.partido', ['idEdicion' => $otra['edicion']->id, 'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $this->partido->id]))
        ->assertSessionHasErrors('partido');
    $this->get(route('cobros.partido', ['idEdicion' => $this->contexto['edicion']->id]))->assertStatus(422);
});
