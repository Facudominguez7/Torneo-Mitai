<?php

use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Support\Facades\DB;

it('expone el singleton global con tarifa por defecto de 25000 ARS y sin activar', function () {
    $configuracion = (new ConfiguracionCobroService)->obtenerConfiguracion();

    expect(DB::connection()->getDatabaseName())->toBe('mitai_testing')
        ->and($configuracion->id)->toBe(1)
        ->and($configuracion->tarifa_ars)->toBe(25000)
        ->and($configuracion->activado_at)->toBeNull()
        ->and(ConfiguracionCobroPlanilla::query()->count())->toBe(1);
});

it('no muta estado al leer la configuración', function () {
    $antes = ConfiguracionCobroPlanilla::query()->findOrFail(1);
    $this->travel(5)->minutes();

    (new ConfiguracionCobroService)->obtenerConfiguracion();

    $despues = ConfiguracionCobroPlanilla::query()->findOrFail(1);
    expect(ConfiguracionCobroPlanilla::query()->count())->toBe(1)
        ->and($despues->tarifa_ars)->toBe($antes->tarifa_ars)
        ->and($despues->activado_at)->toBeNull()
        ->and($despues->updated_at->equalTo($antes->updated_at))->toBeTrue()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('no recrea la configuración global ausente al consultarla', function () {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->delete();

    expect(fn () => (new ConfiguracionCobroService)->obtenerConfiguracion())->toThrow(RuntimeException::class)
        ->and(ConfiguracionCobroPlanilla::query()->count())->toBe(0);
});

it('mantiene la atribución y datos históricos si se elimina al operador', function () {
    $usuario = User::factory()->create();
    $evento = HistorialCobroPlanilla::query()->create([
        'accion' => 'actualizar_tarifa', 'usuario_id' => $usuario->id, 'actor_nombre' => $usuario->name,
        'valores_antes' => ['tarifa_ars' => 25000], 'valores_despues' => ['tarifa_ars' => 30000],
    ]);
    expect($evento->usuario->is($usuario))->toBeTrue();
    $usuario->delete();

    expect($evento->refresh()->usuario_id)->toBeNull()
        ->and($evento->usuario)->toBeNull()
        ->and($evento->actor_nombre)->toBe($usuario->name)
        ->and($evento->valores_antes)->toBe(['tarifa_ars' => 25000])
        ->and($evento->valores_despues)->toBe(['tarifa_ars' => 30000]);
});

it('rechaza editar o borrar eventos mediante instancias Eloquent', function (string $operacion) {
    $usuario = User::factory()->create();
    $evento = HistorialCobroPlanilla::query()->create([
        'accion' => 'actualizar_tarifa', 'usuario_id' => $usuario->id, 'actor_nombre' => $usuario->name,
        'valores_antes' => ['tarifa_ars' => 25000], 'valores_despues' => ['tarifa_ars' => 30000],
    ]);

    expect(fn () => $operacion === 'editar' ? $evento->update(['actor_nombre' => 'Otro operador']) : $evento->delete())
        ->toThrow(LogicException::class);
    expect($evento->fresh()->actor_nombre)->toBe($usuario->name)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
})->with(['editar', 'borrar']);
