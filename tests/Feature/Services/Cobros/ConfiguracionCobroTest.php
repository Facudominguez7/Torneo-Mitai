<?php

use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Helper: usuario persistido con el rol indicado en la columna vigente de DB.
 */
function crearUsuarioConRol(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->forceFill(['rol' => $rol])->save();

    return $usuario->refresh();
}

it('actualiza la tarifa con auditoría de actor y valores antes/después', function () {
    $admin = crearUsuarioConRol('admin');
    $admin->name = 'Nombre Mutado En Memoria';

    $configuracion = (new ConfiguracionCobroService)->actualizarTarifa($admin, 30000);

    expect($configuracion->tarifa_ars)->toBe(30000)
        ->and($configuracion->activado_at)->toBeNull();

    $evento = HistorialCobroPlanilla::query()->sole();

    expect($evento->accion)->toBe('actualizar_tarifa')
        ->and($evento->usuario_id)->toBe($admin->id)
        // El snapshot del actor viene de la fila vigente en DB, no del objeto mutado localmente.
        ->and($evento->actor_nombre)->toBe(User::query()->find($admin->id)->name)
        ->and($evento->actor_nombre)->not->toBe('Nombre Mutado En Memoria')
        ->and($evento->valores_antes)->toBe(['tarifa_ars' => 25000])
        ->and($evento->valores_despues)->toBe(['tarifa_ars' => 30000]);
});

it('activa los cobros con el instante tomado dentro de la transacción', function () {
    $admin = crearUsuarioConRol('admin');
    $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));

    $configuracion = (new ConfiguracionCobroService)->activar($admin);

    expect($configuracion->activado_at?->format('Y-m-d H:i:s'))->toBe('2026-06-01 12:00:00');

    $evento = HistorialCobroPlanilla::query()->sole();

    expect($evento->accion)->toBe('activar_cobros')
        ->and($evento->valores_antes)->toBe(['activado_at' => null])
        ->and($evento->valores_despues)->toBe(['activado_at' => '2026-06-01 12:00:00'])
        ->and($evento->created_at->format('Y-m-d H:i:s'))->toBe('2026-06-01 12:00:00');
});

it('no activa los cobros al actualizar la tarifa', function () {
    $admin = crearUsuarioConRol('admin');

    $configuracion = (new ConfiguracionCobroService)->actualizarTarifa($admin, 31000);

    expect($configuracion->activado_at)->toBeNull();
});

it('activar es idempotente: no reescribe activado_at ni duplica auditoría', function () {
    $admin = crearUsuarioConRol('admin');
    $servicio = new ConfiguracionCobroService;

    $servicio->activar($admin);
    $instanteOriginal = DB::table('configuracion_cobro_planillas')->where('id', 1)->value('activado_at');
    $this->travel(5)->minutes();
    $servicio->activar($admin);

    expect(DB::table('configuracion_cobro_planillas')->where('id', 1)->value('activado_at'))->toBe($instanteOriginal)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'activar_cobros')->count())->toBe(1);
});

it('conserva el instante de activación al actualizar la tarifa después', function () {
    $admin = crearUsuarioConRol('admin');
    $servicio = new ConfiguracionCobroService;

    $servicio->activar($admin);
    $instanteOriginal = DB::table('configuracion_cobro_planillas')->where('id', 1)->value('activado_at');
    $this->travel(5)->minutes();
    $servicio->actualizarTarifa($admin, 32000);

    expect(DB::table('configuracion_cobro_planillas')->where('id', 1)->value('activado_at'))->toBe($instanteOriginal);
});

it('rechaza actores no admin, transitorios o desactualizados', function () {
    $servicio = new ConfiguracionCobroService;
    $regular = crearUsuarioConRol('regular');
    $adminReal = crearUsuarioConRol('admin');

    // Transitorio: objeto no persistido que finge el id de un admin real.
    $falsoTransitorio = new User;
    $falsoTransitorio->id = $adminReal->id;
    $falsoTransitorio->rol = 'admin';

    // Stale: objeto local mutado que finge admin mientras la DB conserva el rol viejo.
    $stale = User::query()->findOrFail($regular->id);
    $stale->rol = 'admin';

    expect(fn () => $servicio->activar($regular))->toThrow(AuthorizationException::class)
        ->and(fn () => $servicio->actualizarTarifa($falsoTransitorio, 30000))->toThrow(AuthorizationException::class)
        ->and(fn () => $servicio->activar($stale))->toThrow(AuthorizationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);

    // Frescura en sentido inverso: la DB ya promovió al usuario, el objeto local es viejo.
    $vistaObsoleta = clone $regular;
    DB::table('users')->where('id', $regular->id)->update(['rol' => 'admin']);
    $servicio->actualizarTarifa($vistaObsoleta, 30000);

    expect(ConfiguracionCobroPlanilla::query()->find(1)->tarifa_ars)->toBe(30000);
});

it('rechaza importes inválidos sin truncarlos', function (mixed $tarifaInvalida) {
    $admin = crearUsuarioConRol('admin');

    expect(fn () => (new ConfiguracionCobroService)->actualizarTarifa($admin, $tarifaInvalida))
        ->toThrow(ValidationException::class);

    expect(ConfiguracionCobroPlanilla::query()->find(1)->tarifa_ars)->toBe(25000)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([
    'cero' => 0,
    'negativo' => -1,
    'decimal' => 25000.5,
    'flotante_con_parte_entera' => 25000.0,
    'texto_numerico' => '25000',
    'texto_miles_ambiguo' => '25.000',
    'booleano_true' => true,
    'booleano_false' => false,
    'nulo' => null,
    'overflow_int_sql' => 2147483648,
]);

it('acepta los límites enteros del campo SQL sin pérdida de precisión', function (int $tarifa) {
    $configuracion = (new ConfiguracionCobroService)->actualizarTarifa(crearUsuarioConRol('admin'), $tarifa);

    expect($configuracion->fresh()->tarifa_ars)->toBe($tarifa)
        ->and(HistorialCobroPlanilla::query()->sole()->valores_despues)->toBe(['tarifa_ars' => $tarifa]);
})->with([1, 2147483647]);

it('no registra evento cuando la tarifa es igual (no-op)', function () {
    $admin = crearUsuarioConRol('admin');

    $configuracion = (new ConfiguracionCobroService)->actualizarTarifa($admin, 25000);

    expect($configuracion->tarifa_ars)->toBe(25000)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('revierte la activación si falla la auditoría en la misma transacción', function () {
    $admin = crearUsuarioConRol('admin');
    HistorialCobroPlanilla::creating(fn () => throw new RuntimeException('fallo audit simulado'));

    expect(fn () => (new ConfiguracionCobroService)->activar($admin))
        ->toThrow(RuntimeException::class);

    expect(ConfiguracionCobroPlanilla::query()->find(1)->activado_at)->toBeNull()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('preserva el historial al eliminar la cuenta del actor', function () {
    $admin = crearUsuarioConRol('admin');

    (new ConfiguracionCobroService)->activar($admin);
    $admin->delete();

    $evento = HistorialCobroPlanilla::query()->sole();

    expect($evento->usuario_id)->toBeNull()
        ->and($evento->actor_nombre)->not->toBeEmpty();
});

it('rechaza la identidad de un actor persistido alterada en memoria', function () {
    $regular = crearUsuarioConRol('regular');
    $admin = crearUsuarioConRol('admin');
    $regular->id = $admin->id;

    expect(fn () => (new ConfiguracionCobroService)->actualizarTarifa($regular, 30000))
        ->toThrow(AuthorizationException::class);

    expect(ConfiguracionCobroPlanilla::query()->findOrFail(1)->tarifa_ars)->toBe(25000)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('revierte la operación cuando un evento veta la persistencia', function (string $operacion, string $superficie) {
    $admin = crearUsuarioConRol('admin');
    $modelo = $superficie === 'configuracion' ? ConfiguracionCobroPlanilla::class : HistorialCobroPlanilla::class;
    $modelo::saving(fn () => false);
    $servicio = new ConfiguracionCobroService;

    expect(fn () => $operacion === 'activar' ? $servicio->activar($admin) : $servicio->actualizarTarifa($admin, 30000))
        ->toThrow(RuntimeException::class);

    $configuracion = $servicio->obtenerConfiguracion();
    expect($configuracion->tarifa_ars)->toBe(25000)
        ->and($configuracion->activado_at)->toBeNull()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['activar', 'actualizar'])->with(['configuracion', 'historial']);

it('falla cerrado si la fila global no existe, sin recrearla', function () {
    DB::table('configuracion_cobro_planillas')->where('id', 1)->delete();
    $servicio = new ConfiguracionCobroService;

    expect(fn () => $servicio->obtenerConfiguracion())->toThrow(RuntimeException::class)
        ->and(fn () => $servicio->activar(crearUsuarioConRol('admin')))->toThrow(RuntimeException::class)
        ->and(DB::table('configuracion_cobro_planillas')->count())->toBe(0);
});
