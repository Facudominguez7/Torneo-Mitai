<?php

use App\Models\CargoPlanilla;
use App\Models\Categoria;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\ProgramacionCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

/**
 * Comportamiento del motor de altas con cargos: create de padre más dos
 * cuotas atómicas sólo con cobros activados, quote de tarifa única,
 * contexto monetario validado y rollback total ante cualquier veto.
 */
it('crea el partido regular con dos cargos de 25000 y un evento generar_cargos cuando el cobro está activo', function () {
    $admin = crearAdminCargos('Admin Activo');
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Mitai Azul');
    $visitante = crearEquipoCargos($contexto, 'Mitai Rojo');
    $this->travelTo('2026-06-01 12:00:00');

    $admin->name = 'Nombre Mutado En Memoria';

    $partido = (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante));

    expect($partido)->toBeInstanceOf(Partido::class)
        ->and($partido->exists)->toBeTrue()
        ->and(Partido::query()->count())->toBe(1)
        ->and(CargoPlanilla::query()->count())->toBe(2);

    $cargoLocal = CargoPlanilla::query()->where('idEquipo', $local->id)->sole();
    $cargoVisitante = CargoPlanilla::query()->where('idEquipo', $visitante->id)->sole();

    expect($cargoLocal->precio_original)->toBeInt()->toBe(25000)
        ->and($cargoVisitante->precio_original)->toBe(25000)
        ->and($cargoLocal->tipo)->toBe('planilla_partido')
        ->and($cargoLocal->parent_id)->toBe($partido->id)
        ->and($cargoLocal->parent_type)->toBe(Partido::class)
        ->and($cargoLocal->snapshot['equipo'])->toBe(['id' => $local->id, 'nombre' => 'Mitai Azul'])
        ->and($cargoLocal->snapshot['oponente'])->toBe(['id' => $visitante->id, 'nombre' => 'Mitai Rojo'])
        ->and($cargoLocal->snapshot['edicion'])->toBe(['id' => $contexto['edicion']->id, 'nombre' => 'Edicion Cobros'])
        ->and($cargoLocal->snapshot['categoria'])->toBe(['id' => $contexto['categoria']->id, 'nombre' => 'Sub 12 Cobros'])
        ->and($cargoLocal->snapshot['cancha'])->toBe(1)
        ->and($cargoLocal->snapshot['fecha_partido'])->toBe('2026-04-05 10:00:00')
        ->and($cargoLocal->snapshot['partido']['public_token'])->toBe($partido->public_token)
        ->and($cargoLocal->snapshot['partido']['es_interzonal'])->toBeFalse()
        ->and($cargoLocal->snapshot['partido']['id_fecha'])->toBe($contexto['fecha']->id);

    $evento = HistorialCobroPlanilla::query()->sole();

    expect($evento->accion)->toBe('generar_cargos')
        ->and($evento->usuario_id)->toBe($admin->id)
        // El snapshot del actor viene de la fila vigente en DB, no del objeto mutado localmente.
        ->and($evento->actor_nombre)->toBe('Admin Activo')
        ->and($evento->actor_nombre)->not->toBe('Nombre Mutado En Memoria')
        ->and($evento->valores_antes)->toBe([])
        ->and($evento->valores_despues['parent']['id'])->toBe($partido->id)
        ->and($evento->valores_despues['cargos'])->toBe([$cargoLocal->id, $cargoVisitante->id])
        ->and($evento->valores_despues['equipos'])->toBe([$local->id, $visitante->id])
        ->and($evento->valores_despues['precio_ars'])->toBe(25000)
        ->and($evento->valores_despues['fecha_partido'])->toBe('2026-04-05 10:00:00')
        ->and($evento->created_at->format('Y-m-d H:i:s'))->toBe('2026-06-01 12:00:00');
});

it('crea el partido interzonal sin fecha con dos cargos y snapshot sin inventar horario', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos('Edicion Interzonal Cobros');
    $local = crearEquipoCargos($contexto, 'Inter Azul');
    $visitante = crearEquipoCargos($contexto, 'Inter Blanco');

    $partido = (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante, [
        'es_interzonal' => true,
        'horario_datetime' => null,
    ]));

    expect($partido->esInterzonal())->toBeTrue()
        ->and($partido->horario_datetime)->toBeNull()
        ->and(CargoPlanilla::query()->count())->toBe(2);

    $cargo = CargoPlanilla::query()->where('idEquipo', $local->id)->sole();

    expect($cargo->precio_original)->toBe(25000)
        ->and($cargo->snapshot['fecha_partido'])->toBeNull()
        ->and($cargo->snapshot['partido']['es_interzonal'])->toBeTrue()
        ->and(HistorialCobroPlanilla::query()->sole()->valores_despues['fecha_partido'])->toBeNull();
});

it('crea la instancia final con dos cargos morfeando a InstanciaFinal', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos('Edicion Final Cobros');
    $local = crearEquipoCargos($contexto, 'Final Azul');
    $visitante = crearEquipoCargos($contexto, 'Final Blanco');

    $final = (new ProgramacionCobroService)->crearInstanciaFinal($admin, crearDatosFinalCargos($contexto, $local, $visitante));

    expect($final)->toBeInstanceOf(InstanciaFinal::class)
        ->and(InstanciaFinal::query()->count())->toBe(1)
        ->and(CargoPlanilla::query()->count())->toBe(2);

    $cargo = CargoPlanilla::query()->where('idEquipo', $local->id)->sole();

    expect($cargo->tipo)->toBe('planilla_instancia_final')
        ->and($cargo->parent_type)->toBe(InstanciaFinal::class)
        ->and($cargo->parent_id)->toBe($final->id)
        ->and($cargo->snapshot['fecha_partido'])->toBe('2026-04-05 10:00:00')
        ->and($cargo->snapshot['cancha'])->toBe('1')
        ->and($cargo->snapshot['partido']['es_interzonal'])->toBeNull()
        ->and($cargo->snapshot['partido']['id_fecha'])->toBeNull()
        ->and($cargo->snapshot['partido']['public_token'])->toBe($final->public_token)
        ->and(HistorialCobroPlanilla::query()->sole()->valores_despues['parent']['clase'])->toBe(InstanciaFinal::class);
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

it('rechaza equipos iguales con ValidationException en ambos estados del cobro', function (bool $activo) {
    $admin = crearAdminCargos();
    if ($activo) {
        activarCobrosCargos();
    }

    $contexto = crearContextoCargos();
    $mismo = crearEquipoCargos($contexto, 'Duplicado Azul');

    expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $mismo, $mismo)))
        ->toThrow(ValidationException::class)
        ->and(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([
    'cobro activo' => [true],
    'cobro inactivo' => [false],
]);

it('rechaza contextos monetarios fuera de ámbito sin crear el padre', function (Closure $escenario) {
    $admin = crearAdminCargos();
    $datos = $escenario();

    expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, $datos))
        ->toThrow(ValidationException::class)
        ->and(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([
    'equipo sin inscripción' => function (): array {
        $contexto = crearContextoCargos('Ambito Sin Pivot');
        $local = crearEquipoCargos($contexto, 'Pivot Azul');
        $sueltos = Equipo::create([
            'idCategoria' => $contexto['categoria']->id,
            'idEdicion' => $contexto['edicion']->id,
            'nombre' => 'Pivot Sueltos',
        ]);

        return crearDatosPartidoCargos($contexto, $local, $sueltos);
    },
    'visitante inscripto en otra edición' => function (): array {
        $contexto = crearContextoCargos('Ambito Local');
        $otro = crearContextoCargos('Ambito Otro');
        $local = crearEquipoCargos($contexto, 'Edicion Azul');
        $visitante = crearEquipoCargos($otro, 'Edicion Blanco');

        return crearDatosPartidoCargos($contexto, $local, $visitante);
    },
    'visitante inscripto en otra categoría de la misma edición' => function (): array {
        $contexto = crearContextoCargos('Ambito Categorias');
        $otraCategoria = Categoria::create([
            'idEdicion' => $contexto['edicion']->id,
            'nombreCategoria' => 'Sub 14 Otra',
        ]);
        $local = crearEquipoCargos($contexto, 'Categoria Azul');
        $visitante = crearEquipoCargos($contexto, 'Categoria Blanco');
        EquipoEdicion::query()->where('idEquipo', $visitante->id)->update(['idCategoria' => $otraCategoria->id]);

        return crearDatosPartidoCargos($contexto, $local, $visitante);
    },
    'categoría que pertenece a otra edición' => function (): array {
        $contexto = crearContextoCargos('Ambito Categoria Cruzada');
        $otro = crearContextoCargos('Ambito Otra Edicion');
        $local = crearEquipoCargos($contexto, 'Cruzada Azul');
        $visitante = crearEquipoCargos($contexto, 'Cruzada Blanco');

        return crearDatosPartidoCargos($contexto, $local, $visitante, ['idCategoria' => $otro['categoria']->id]);
    },
]);

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

it('si el alta del padre es vetada todo se revierte', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'VetoPadre Azul');
    $visitante = crearEquipoCargos($contexto, 'VetoPadre Blanco');

    Partido::creating(function (): bool {
        return false;
    });

    try {
        expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante)))
            ->toThrow(RuntimeException::class);
    } finally {
        Partido::flushEventListeners();
        Partido::clearBootedModels();
    }

    expect(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('si el segundo cargo es vetado se revierten padre, cargos y evento', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'VetoCargo Azul');
    $visitante = crearEquipoCargos($contexto, 'VetoCargo Blanco');

    $intentos = 0;
    CargoPlanilla::creating(function () use (&$intentos): bool {
        $intentos++;

        return $intentos < 2;
    });

    try {
        expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante)))
            ->toThrow(RuntimeException::class)
            ->and($intentos)->toBe(2);
    } finally {
        CargoPlanilla::flushEventListeners();
        CargoPlanilla::clearBootedModels();
    }

    expect(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('si el evento generar_cargos falla se revierte el alta completa', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'VetoEvento Azul');
    $visitante = crearEquipoCargos($contexto, 'VetoEvento Blanco');

    HistorialCobroPlanilla::creating(function (): bool {
        return false;
    });

    try {
        expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante)))
            ->toThrow(RuntimeException::class);
    } finally {
        HistorialCobroPlanilla::flushEventListeners();
        HistorialCobroPlanilla::clearBootedModels();
    }

    expect(Partido::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});
