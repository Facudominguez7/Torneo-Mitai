<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\ProgramacionCobroService;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/FixturesCargos.php';

/**
 * Comportamiento del motor de altas con cargos: create de padre más dos
 * cuotas atómicas sólo con cobros activados, quote de tarifa única,
 * contexto monetario validado y rollback total ante cualquier veto.
 */
it('rechaza la emisión activa mientras se prepara el motor sin crear registros', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $datos = crearDatosPartidoCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'));

    expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, $datos))
        ->toThrow(RuntimeException::class, 'La emisión activa de cargos aún no está integrada.')
        ->and(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('sin activar crea el partido con cero cargos y cero auditoría monetaria', function () {
    $admin = crearAdminCargos();
    $contexto = crearContextoCargos();

    $partido = (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos(
        $contexto,
        crearEquipoCargos($contexto, 'Sin Cargo Azul'),
        crearEquipoCargos($contexto, 'Sin Cargo Blanco'),
    ));

    expect($partido)->toBeInstanceOf(Partido::class)
        ->and(Partido::query()->count())->toBe(1)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('sin activar la instancia final también queda sin cargos', function () {
    $admin = crearAdminCargos();
    $contexto = crearContextoCargos();

    $final = (new ProgramacionCobroService)->crearInstanciaFinal($admin, crearDatosFinalCargos(
        $contexto,
        crearEquipoCargos($contexto, 'Final Sin Cargo A'),
        crearEquipoCargos($contexto, 'Final Sin Cargo B'),
    ));

    expect($final)->toBeInstanceOf(InstanciaFinal::class)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('autoriza sólo a un admin vigente en db sin escribir nada', function (string $escenario) {
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Permisos Azul');
    $visitante = crearEquipoCargos($contexto, 'Permisos Blanco');
    $datos = crearDatosPartidoCargos($contexto, $local, $visitante);
    $persistido = crearAdminCargos('Admin Permisos');

    $actor = match ($escenario) {
        'sin rol admin' => crearUsuarioConRolCargos('regular'),
        'rol revocado en db' => tap($persistido, function (User $admin): void {
            User::query()->whereKey($admin->id)->update(['rol' => 'editor']);
        }),
        'transitorio con id fingido' => (function () use ($persistido): User {
            $fantasma = new User;
            $fantasma->forceFill(['id' => $persistido->id, 'rol' => 'admin']);

            return $fantasma;
        })(),
        'eliminado' => tap($persistido, function (User $admin): void {
            User::query()->whereKey($admin->id)->delete();
        }),
        'pk sucia' => tap($persistido, function (User $admin): void {
            $admin->id = 999999;
        }),
    };

    expect(fn () => (new ProgramacionCobroService)->crearPartido($actor, $datos))
        ->toThrow(AuthorizationException::class)
        ->and(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([
    'sin rol admin',
    'rol revocado en db',
    'transitorio con id fingido',
    'eliminado',
    'pk sucia',
]);

it('falla cerrado si falta la fila de configuración global sin crear nada', function () {
    $admin = crearAdminCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'FailClosed Azul');
    $visitante = crearEquipoCargos($contexto, 'FailClosed Blanco');
    ConfiguracionCobroPlanilla::query()->whereKey(1)->delete();

    expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante)))
        ->toThrow(RuntimeException::class)
        ->and(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0)
        ->and(ConfiguracionCobroPlanilla::query()->count())->toBe(0);
});
