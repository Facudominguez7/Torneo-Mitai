<?php

use App\Models\CargoPlanilla;
use App\Models\EquipoEdicion;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Services\Cobros\ProgramacionCobroService;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

it('acepta el interzonal real con grupo fecha y cancha sin definir', function (bool $activo) {
    $admin = crearAdminCargos();
    if ($activo) {
        activarCobrosCargos();
    }
    $contexto = crearContextoCargos();
    $datos = crearDatosPartidoCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'), [
        'es_interzonal' => true, 'idGrupo' => null, 'horario_datetime' => null, 'cancha' => null,
    ]);

    $partido = (new ProgramacionCobroService)->crearPartido($admin, $datos);
    expect($partido->fresh()->idGrupo)->toBeNull()
        ->and($partido->horario_datetime)->toBeNull()
        ->and($partido->cancha)->toBeNull()
        ->and(CargoPlanilla::query()->count())->toBe($activo ? 2 : 0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe($activo ? 1 : 0);
    if ($activo) {
        expect(CargoPlanilla::query()->first()->snapshot['cancha'])->toBeNull();
    }
})->with([true, false]);

it('acepta los ids y cancha numéricos que llegan de formularios HTML', function (string $tipo) {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Local');
    $visitante = crearEquipoCargos($contexto, 'Visitante');
    $datos = $tipo === 'final' ? crearDatosFinalCargos($contexto, $local, $visitante, ['cancha' => 1, 'horario' => '2026-10-07T10:00']) : crearDatosPartidoCargos($contexto, $local, $visitante);
    foreach ($datos as $clave => $valor) {
        if (is_int($valor)) {
            $datos[$clave] = (string) $valor;
        }
    }

    $servicio = new ProgramacionCobroService;
    $partido = $tipo === 'final' ? $servicio->crearInstanciaFinal($admin, $datos) : $servicio->crearPartido($admin, $datos);
    expect($partido->exists)->toBeTrue()->and(CargoPlanilla::query()->count())->toBe(2)
        ->and(CargoPlanilla::query()->first()->snapshot['fecha_partido'])->toBe($tipo === 'final' ? '2026-10-07 10:00:00' : '2026-04-05 10:00:00');
})->with(['regular', 'final']);

it('admite una cancha final validada como entero PHP', function () {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $final = (new ProgramacionCobroService)->crearInstanciaFinal($admin, crearDatosFinalCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'), ['cancha' => 1]));
    expect($final->fresh()->cancha)->toBe('1')->and(CargoPlanilla::query()->count())->toBe(2);
});

it('comprueba identidades inscritas y no la cantidad bruta de pivots', function (bool $visitanteInscrito) {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Local');
    $visitante = crearEquipoCargos($contexto, 'Visitante');
    if (! $visitanteInscrito) {
        EquipoEdicion::query()->where('idEquipo', $visitante->id)->delete();
    }
    EquipoEdicion::create(['idEquipo' => $local->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]);
    $crear = fn () => (new ProgramacionCobroService)->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante));
    if ($visitanteInscrito) {
        $crear();
        expect(CargoPlanilla::query()->count())->toBe(2);
    } else {
        expect($crear)->toThrow(ValidationException::class)
            ->and(Partido::query()->count())->toBe(0)
            ->and(CargoPlanilla::query()->count())->toBe(0)
            ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
    }
})->with([true, false]);

it('valida el precio original antes de cualquier cast monetario', function (mixed $precio) {
    expect(fn () => CargoPlanilla::query()->create([
        'tipo' => 'planilla_partido', 'parent_type' => Partido::class, 'parent_id' => 1,
        'idEquipo' => 1, 'idEdicion' => 1, 'idCategoria' => 1, 'precio_original' => $precio, 'snapshot' => [],
    ]))->toThrow(RuntimeException::class, 'El precio del cargo')->and(CargoPlanilla::query()->count())->toBe(0);
})->with([[25000.75], [25000.0], ['25000'], ['25000.75'], [true], [2147483648]]);

it('rechaza horarios inválidos antes de persistir el encuentro y sus cargos', function (string $tipo) {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Local');
    $visitante = crearEquipoCargos($contexto, 'Visitante');
    $servicio = new ProgramacionCobroService;
    $crear = $tipo === 'final'
        ? fn () => $servicio->crearInstanciaFinal($admin, crearDatosFinalCargos($contexto, $local, $visitante, ['horario' => 'no-es-fecha']))
        : fn () => $servicio->crearPartido($admin, crearDatosPartidoCargos($contexto, $local, $visitante, ['horario_datetime' => 'no-es-fecha']));
    expect($crear)->toThrow(ValidationException::class)
        ->and(Partido::query()->count())->toBe(0)
        ->and(InstanciaFinal::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['regular', 'final']);

it('rechaza identificadores no enteros sin perder precisión silenciosamente', function (mixed $id) {
    $admin = crearAdminCargos();
    $contexto = crearContextoCargos();
    $datos = crearDatosPartidoCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'), ['idEquipoLocal' => $id]);
    expect(fn () => (new ProgramacionCobroService)->crearPartido($admin, $datos))->toThrow(ValidationException::class)
        ->and(Partido::query()->count())->toBe(0)->and(CargoPlanilla::query()->count())->toBe(0);
})->with([[1.0], [1.5], [true], ['1.5'], ['1e0'], [2147483648]]);

it('conserva los resultados precargados permitidos por el alta regular', function () {
    $admin = crearAdminCargos();
    $contexto = crearContextoCargos();
    $datos = crearDatosPartidoCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'), ['golesEquipoLocal' => '2', 'golesEquipoVisitante' => '0', 'jugado' => '1']);
    $partido = (new ProgramacionCobroService)->crearPartido($admin, $datos);
    expect((int) $partido->fresh()->golesEquipoLocal)->toBe(2)
        ->and((int) $partido->golesEquipoVisitante)->toBe(0)->and((int) $partido->jugado)->toBe(1);
});

it('no inventa fecha cuando el horario interzonal está vacío', function (string $horario) {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $datos = crearDatosPartidoCargos($contexto, crearEquipoCargos($contexto, 'Local'), crearEquipoCargos($contexto, 'Visitante'), ['idGrupo' => null, 'es_interzonal' => true, 'horario_datetime' => $horario]);
    $partido = (new ProgramacionCobroService)->crearPartido($admin, $datos);
    expect($partido->fresh()->horario_datetime)->toBeNull()
        ->and(CargoPlanilla::query()->first()->snapshot['fecha_partido'])->toBeNull()
        ->and(HistorialCobroPlanilla::query()->sole()->valores_despues['fecha_partido'])->toBeNull();
})->with(['', ' ', '   ']);

it('rechaza campos de identidad ausentes sin crear historia parcial', function (string $tipo, string $campo) {
    $admin = crearAdminCargos();
    activarCobrosCargos();
    $contexto = crearContextoCargos();
    $local = crearEquipoCargos($contexto, 'Local');
    $visitante = crearEquipoCargos($contexto, 'Visitante');
    $datos = $tipo === 'final' ? crearDatosFinalCargos($contexto, $local, $visitante) : crearDatosPartidoCargos($contexto, $local, $visitante);
    unset($datos[$campo]);
    $servicio = new ProgramacionCobroService;
    $crear = $tipo === 'final' ? fn () => $servicio->crearInstanciaFinal($admin, $datos) : fn () => $servicio->crearPartido($admin, $datos);
    expect($crear)->toThrow(ValidationException::class)
        ->and(Partido::query()->count())->toBe(0)->and(InstanciaFinal::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([['regular', 'idEquipoLocal'], ['regular', 'idEquipoVisitante'], ['regular', 'idEdicion'], ['regular', 'idCategoria'], ['regular', 'idFechas'], ['regular', 'idGrupo'], ['final', 'idFase']]);
