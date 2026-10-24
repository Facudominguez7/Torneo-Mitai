<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->withSession(['_token' => 'cobros-operaciones'])->withHeaders(['X-CSRF-TOKEN' => 'cobros-operaciones']);
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo operaciones');
    $rival = crearEquipoCargos($this->contexto, 'Rival operaciones');
    $this->admin = crearAdminCargos();
    activarCobrosCargos();
    $this->cargos = collect([0, 1])->map(function (int $indice) use ($rival): CargoPlanilla {
        $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rival, [
            'horario_datetime' => '2026-04-0'.($indice + 5).' 10:00:00',
        ]));

        return CargoPlanilla::create([
            'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
            'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
            'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
        ]);
    });
    $this->entrada = [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
        'cargo_id' => $this->cargos[0]->id, 'importe_ars' => '10000', 'medio' => 'efectivo',
        'clave_idempotencia' => (string) Str::uuid(),
    ];
});

it('registra un pago parcial por HTTP y conserva su historial al reintentar', function (): void {
    $this->actingAs($this->admin);
    foreach ([1, 2] as $intento) {
        $this->postJson('/Panel/cobros/pagos', $this->entrada)->assertSuccessful()
            ->assertJsonPath('pago.importe_ars', 10000)->assertJsonPath('pago.aplicado_ars', 10000)
            ->assertJsonPath('pago.aplicado_origen_ars', 10000)->assertJsonPath('pago.deuda_cargo_ars', 15000)
            ->assertJsonPath('pago.credito_equipo_ars', 0)->assertJsonPath('pago.credito_pago_ars', 0);
    }
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 1);
    $this->assertDatabaseCount('historial_cobro_planillas', 1);
    expect((int) PagoPlanilla::sole()->importe_ars)->toBe(10000)
        ->and((int) AplicacionPagoPlanilla::sole()->importe_ars)->toBe(10000);
});

it('distribuye el excedente dentro de la cuenta sin duplicarlo en replay', function (): void {
    $this->actingAs($this->admin);
    $entrada = array_replace($this->entrada, ['importe_ars' => '60000', 'cargo_id' => $this->cargos[1]->id]);
    foreach ([1, 2] as $intento) {
        $this->postJson('/Panel/cobros/pagos', $entrada)->assertSuccessful()
            ->assertJsonPath('pago.importe_ars', 60000)->assertJsonPath('pago.aplicado_ars', 50000)
            ->assertJsonPath('pago.aplicado_origen_ars', 25000)->assertJsonPath('pago.deuda_cargo_ars', 0)
            ->assertJsonPath('pago.credito_equipo_ars', 10000)->assertJsonPath('pago.credito_pago_ars', 10000);
    }
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 2);
    expect((int) AplicacionPagoPlanilla::sum('importe_ars'))->toBe(50000);
    foreach ($this->cargos as $cargo) {
        expect((int) AplicacionPagoPlanilla::where('cargo_id', $cargo->id)->sum('importe_ars'))->toBe(25000);
    }
    $partidoNuevo = $this->cargos[0]->parent->replicate(['public_token']);
    $partidoNuevo->save();
    $nuevo = CargoPlanilla::create(array_replace($this->cargos[0]->only(['tipo', 'parent_type', 'parent_id', 'idEquipo', 'idEdicion', 'idCategoria', 'precio_original', 'snapshot']), ['parent_id' => $partidoNuevo->id]));
    $this->postJson('/Panel/cobros/pagos', $entrada)->assertSuccessful()->assertJsonPath('pago.credito_pago_ars', 10000);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 2);
    expect((int) AplicacionPagoPlanilla::where('cargo_id', $nuevo->id)->sum('importe_ars'))->toBe(0);
});

it('prioriza seleccionado y limita crédito al equipo y edición del ingreso', function (): void {
    $rival = CargoPlanilla::create(array_replace($this->cargos[0]->only(['tipo', 'parent_type', 'parent_id', 'idEquipo', 'idEdicion', 'idCategoria', 'precio_original', 'snapshot']), ['idEquipo' => $this->cargos[0]->parent->idEquipoVisitante]));
    $this->actingAs($this->admin);
    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, ['importe_ars' => '40000', 'cargo_id' => $this->cargos[1]->id]))->assertSuccessful();
    expect((int) AplicacionPagoPlanilla::where('cargo_id', $this->cargos[1]->id)->sum('importe_ars'))->toBe(25000)
        ->and((int) AplicacionPagoPlanilla::where('cargo_id', $this->cargos[0]->id)->sum('importe_ars'))->toBe(15000);
    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, ['cargo_id' => $this->cargos[1]->id, 'clave_idempotencia' => (string) Str::uuid()]))->assertUnprocessable();
    $this->assertDatabaseCount('pago_planillas', 1);
    expect((int) AplicacionPagoPlanilla::where('cargo_id', $rival->id)->sum('importe_ars'))->toBe(0);
});

it('revierte el ingreso si falla la distribución automática', function (): void {
    $this->mock(CreditosCobroService::class, function ($mock): void {
        $mock->shouldReceive('aplicarCredito')->once()->andThrow(new RuntimeException('Fallo sintético'));
    });
    $this->actingAs($this->admin)->postJson('/Panel/cobros/pagos', array_replace($this->entrada, ['importe_ars' => '40000']))->assertStatus(500);
    foreach (['pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas', 'cuenta_cobro_planillas'] as $tabla) {
        $this->assertDatabaseCount($tabla, 0);
    }
});

it('rechaza importes inválidos antes de escribir dinero', function (mixed $importe): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/pagos', array_replace($this->entrada, [
        'importe_ars' => $importe,
    ]))->assertUnprocessable()->assertJsonValidationErrors('importe_ars');
    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
})->with(['decimal texto' => ['100.5'], 'decimal JSON' => [100.5], 'cero' => [0],
    'negativo' => [-1], 'booleano' => [true], 'desborde' => ['2147483648'], 'array' => [[100]], 'nulo' => [null]]);

it('rechaza alcance ajeno y UUID reutilizado con otro importe sin efectos secundarios', function (): void {
    $this->actingAs($this->admin);
    $ajeno = crearContextoCargos('Edición ajena');
    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, [
        'idEdicion' => $ajeno['edicion']->id,
    ]))->assertUnprocessable();
    $otroEquipo = crearEquipoCargos($this->contexto, 'Equipo ajeno');
    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, [
        'idEquipo' => $otroEquipo->id,
    ]))->assertUnprocessable();
    $this->assertDatabaseCount('pago_planillas', 0);
    $this->postJson('/Panel/cobros/pagos', $this->entrada)->assertSuccessful();
    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, [
        'importe_ars' => '12000',
    ]))->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 1);
});

it('exime solo el residual y registra una política futura con motivo literal', function (): void {
    $this->actingAs($this->admin);
    $this->postJson('/Panel/cobros/pagos', $this->entrada)->assertSuccessful();
    $entrada = $this->entrada + ['motivo' => "  Acordado\n literalmente  "];
    $this->postJson('/Panel/cobros/exenciones', $entrada)->assertSuccessful();
    $this->postJson('/Panel/cobros/exenciones', $entrada)->assertUnprocessable();
    $this->postJson('/Panel/cobros/politicas', $entrada)->assertSuccessful();
    $this->postJson('/Panel/cobros/politicas', $entrada)->assertUnprocessable();
    $this->assertDatabaseHas('exencion_cobro_planillas', ['cargo_id' => $this->cargos[0]->id, 'importe_ars' => 15000, 'motivo' => $entrada['motivo']]);
    $this->assertDatabaseHas('politica_exencion_cobro_planillas', ['idEquipo' => $this->equipo->id, 'motivo' => $entrada['motivo']]);
    $this->assertDatabaseCount('pago_planillas', 1);
    expect((int) AplicacionPagoPlanilla::sum('importe_ars'))->toBe(10000);
    $this->get('/Panel/cobros/detalle?'.http_build_query($this->entrada))->assertOk()
        ->assertSee('registrar_pago')->assertSee('eximir_cargo')->assertSee('registrar_politica_exencion')
        ->assertSee('No hay reversión');
});

it('guardar tarifa no activa y activar requiere confirmación explícita', function (): void {
    ConfiguracionCobroPlanilla::whereKey(1)->update(['activado_at' => null]);
    $this->actingAs($this->admin)->postJson('/Panel/cobros/tarifa', ['tarifa_ars' => '35000'])->assertSuccessful();
    expect(ConfiguracionCobroPlanilla::find(1)->activado_at)->toBeNull();
    $this->postJson('/Panel/cobros/tarifa', ['tarifa_ars' => '35000.5'])->assertUnprocessable();
    $this->postJson('/Panel/cobros/activar')->assertUnprocessable();
    expect(ConfiguracionCobroPlanilla::find(1)->activado_at)->toBeNull();
    $this->postJson('/Panel/cobros/activar', ['confirmar' => true])->assertSuccessful();
    $this->postJson('/Panel/cobros/activar', ['confirmar' => true])->assertSuccessful();
    $this->assertDatabaseCount('historial_cobro_planillas', 2);
});

it('exige CSRF y autorización en cada operación y detalle', function (): void {
    $this->actingAs($this->admin)->withHeaders(['X-CSRF-TOKEN' => 'incorrecto'])
        ->postJson('/Panel/cobros/pagos', $this->entrada)->assertStatus(419);
    $this->withHeaders(['X-CSRF-TOKEN' => 'cobros-operaciones']);
    foreach (['pagos', 'exenciones', 'politicas', 'tarifa', 'activar'] as $operacion) {
        $this->actingAs(crearUsuarioConRolCargos('regular'))->post('/Panel/cobros/'.$operacion, $this->entrada)->assertRedirect('/');
    }
    $this->actingAs(crearUsuarioConRolCargos('regular'))->get('/Panel/cobros/detalle?'.http_build_query($this->entrada))->assertRedirect('/');
    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
});

it('muestra un error genérico sin divulgar fallos inesperados', function (): void {
    $this->mock(PagosCobroService::class, function ($mock): void {
        $mock->shouldReceive('registrarPago')->once()->andThrow(new RuntimeException('SECRET INTERNE'));
    });
    $this->actingAs($this->admin)->postJson('/Panel/cobros/pagos', $this->entrada)->assertStatus(500)
        ->assertJsonPath('message', 'No se pudo completar la operación. Revisá e intentá nuevamente.')
        ->assertDontSee('SECRET INTERNE');
    $this->assertDatabaseCount('pago_planillas', 0);
});

it('impide pagos de visitantes y usuarios no administradores', function (): void {
    $this->post('/Panel/cobros/pagos', $this->entrada)->assertRedirect(route('login'));
    $this->actingAs(crearUsuarioConRolCargos('regular'))
        ->post('/Panel/cobros/pagos', $this->entrada)->assertRedirect('/');
    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
});
