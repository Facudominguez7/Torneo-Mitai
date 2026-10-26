<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->withSession(['_token' => 'cobros-combinado'])->withHeaders(['X-CSRF-TOKEN' => 'cobros-combinado']);
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo combinado');
    $rival = crearEquipoCargos($this->contexto, 'Rival combinado');
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
        'cargo_id' => $this->cargos[0]->id, 'importe_efectivo_ars' => '10000', 'importe_mercado_pago_ars' => '5000',
        'clave_idempotencia' => (string) Str::uuid(),
    ];
});

it('registra efectivo y mercado pago como dos ingresos de una sola operación sin duplicar en replay', function (): void {
    $this->actingAs($this->admin);
    foreach ([1, 2] as $intento) {
        $respuesta = $this->postJson('/Panel/cobros/pagos', $this->entrada)->assertSuccessful()
            ->assertJsonCount(2, 'pagos')
            ->assertJsonPath('pagos.0.medio', 'mercado_pago')->assertJsonPath('pagos.0.importe_ars', 5000)
            ->assertJsonPath('pagos.1.medio', 'efectivo')->assertJsonPath('pagos.1.importe_ars', 10000)
            ->assertJsonPath('pago.deuda_cargo_ars', 10000)->assertJsonPath('pago.credito_equipo_ars', 0);
    }

    expect(PagoPlanilla::query()->orderBy('id')->pluck('importe_ars', 'medio')->all())->toBe(['mercado_pago' => 5000, 'efectivo' => 10000])
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->cargos[0]->id)->sum('importe_ars'))->toBe(15000)
        ->and(PagoPlanilla::query()->pluck('clave_idempotencia')->unique())->toHaveCount(2)
        ->and(PagoPlanilla::query()->pluck('clave_idempotencia'))->toContain(strtolower($this->entrada['clave_idempotencia']));
    $this->assertDatabaseCount('historial_cobro_planillas', 2);
});

it('distribuye el excedente combinado a otras deudas del equipo por fecha', function (): void {
    $this->actingAs($this->admin);
    $entrada = array_replace($this->entrada, ['importe_efectivo_ars' => '20000', 'importe_mercado_pago_ars' => '15000']);

    $this->postJson('/Panel/cobros/pagos', $entrada)->assertSuccessful()
        ->assertJsonPath('pago.deuda_cargo_ars', 0)->assertJsonPath('pago.credito_equipo_ars', 0);

    expect((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->cargos[0]->id)->sum('importe_ars'))->toBe(25000)
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->cargos[1]->id)->sum('importe_ars'))->toBe(10000)
        ->and((int) PagoPlanilla::query()->sum('importe_ars'))->toBe(35000);
});

it('registra un único medio con los importes nuevos igual que un pago simple', function (): void {
    $this->actingAs($this->admin);
    $entrada = array_replace($this->entrada, ['importe_efectivo_ars' => '', 'importe_mercado_pago_ars' => '25000']);

    $this->postJson('/Panel/cobros/pagos', $entrada)->assertSuccessful()
        ->assertJsonCount(1, 'pagos')->assertJsonPath('pago.deuda_cargo_ars', 0);

    expect(PagoPlanilla::sole()->only(['medio', 'importe_ars', 'clave_idempotencia']))
        ->toBe(['medio' => 'mercado_pago', 'importe_ars' => 25000, 'clave_idempotencia' => strtolower($this->entrada['clave_idempotencia'])]);
});

it('rechaza pagos sin importes, con transferencia o con medios que superan solos la deuda sin escribir dinero', function (array $cambios, string $campo): void {
    $this->actingAs($this->admin);

    $this->postJson('/Panel/cobros/pagos', array_replace($this->entrada, $cambios))->assertUnprocessable()->assertJsonValidationErrors($campo);

    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
    $this->assertDatabaseCount('historial_cobro_planillas', 0);
})->with([
    'sin importes' => [['importe_efectivo_ars' => '', 'importe_mercado_pago_ars' => ''], 'importe_efectivo_ars'],
    'importes en cero' => [['importe_efectivo_ars' => '0', 'importe_mercado_pago_ars' => '0'], 'importe_efectivo_ars'],
    'importe con decimales' => [['importe_mercado_pago_ars' => '50.5'], 'importe_mercado_pago_ars'],
    'transferencia' => [['importe_efectivo_ars' => null, 'importe_mercado_pago_ars' => null, 'importe_ars' => '10000', 'medio' => 'transferencia'], 'medio'],
    'cada medio cubre la deuda' => [['importe_efectivo_ars' => '25000', 'importe_mercado_pago_ars' => '25000'], 'importe_efectivo_ars'],
]);

it('no conserva ningún ingreso si falla el segundo medio', function (): void {
    $this->actingAs($this->admin);
    $real = app(PagosCobroService::class);
    $llamadas = 0;
    $this->mock(PagosCobroService::class, function ($mock) use ($real, &$llamadas): void {
        $mock->shouldReceive('registrarPago')->twice()->andReturnUsing(function (...$argumentos) use ($real, &$llamadas): PagoPlanilla {
            if (++$llamadas === 2) {
                throw new RuntimeException('Falla simulada del segundo medio.');
            }

            return $real->registrarPago(...$argumentos);
        });
    });

    $this->postJson('/Panel/cobros/pagos', $this->entrada)->assertServerError();

    $this->assertDatabaseCount('pago_planillas', 0);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 0);
    expect(HistorialCobroPlanilla::query()->count())->toBe(0);
});
