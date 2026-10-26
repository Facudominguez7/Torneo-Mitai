<?php

use App\Models\CargoPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use App\Services\Cobros\ProgramacionCobroService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/FixturesCargos.php';

function filasMaterializacion(): array
{
    $estado = [];
    foreach (['partidos', 'instancias_finales', 'cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'exencion_cobro_planillas', 'historial_cobro_planillas', 'politica_exencion_cobro_planillas', 'cuenta_cobro_planillas'] as $tabla) {
        $estado[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all();
    }

    return $estado;
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Local Politica');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante Politica');
    $this->admin = crearAdminCargos();
    activarCobrosCargos();
    $this->crear = function (string $tipo): Partido|InstanciaFinal {
        $servicio = app(ProgramacionCobroService::class);

        return $tipo === 'final'
            ? $servicio->crearInstanciaFinal($this->admin, crearDatosFinalCargos($this->contexto, $this->local, $this->visitante))
            : $servicio->crearPartido($this->admin, crearDatosPartidoCargos($this->contexto, $this->local, $this->visitante, $tipo === 'interzonal' ? ['es_interzonal' => true, 'horario_datetime' => null] : []));
    };
});

it('materializa solo nuevos cargos del equipo con politica sin tocar historia previa', function (string $tipo, ?string $motivo): void {
    $anterior = ($this->crear)('regular');
    $cargoAnterior = CargoPlanilla::query()->where('parent_id', $anterior->id)->where('idEquipo', $this->local->id)->sole();
    app(PagosCobroService::class)->registrarPago($this->admin, $cargoAnterior->id, 10000, 'efectivo', (string) Str::uuid());
    $cargoRivalAnterior = CargoPlanilla::query()->where('parent_id', $anterior->id)->where('idEquipo', $this->visitante->id)->sole();
    app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->visitante->id, $cargoRivalAnterior->id);
    $politica = app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->local->id, $this->contexto['edicion']->id, $motivo);
    $antes = filasMaterializacion();
    $this->travelTo(now()->startOfSecond());
    User::query()->whereKey($this->admin->id)->update(['name' => 'Programador vigente']);
    $nuevo = ($this->crear)($tipo);
    $cargos = CargoPlanilla::query()->where('parent_type', $nuevo::class)->where('parent_id', $nuevo->id)->get();
    expect($cargos)->toHaveCount(2);
    $cargoLocal = $cargos->firstWhere('idEquipo', $this->local->id);
    $cargoVisitante = $cargos->firstWhere('idEquipo', $this->visitante->id);
    $exencion = ExencionCobroPlanilla::query()->where('cargo_id', $cargoLocal->id)->sole();
    expect($exencion->politica_id)->toBe($politica->id)->and($exencion->politica->is($politica))->toBeTrue()
        ->and($exencion->importe_ars)->toBe(25000)->and($exencion->motivo)->toBe($motivo)
        ->and($exencion->usuario_id)->toBe($this->admin->id)->and($exencion->actor_nombre)->toBe('Programador vigente')
        ->and($exencion->created_at->equalTo(now()))->toBeTrue()
        ->and($cargoLocal->precio_original)->toBe(25000)->and($cargoVisitante->precio_original)->toBe(25000);
    $this->assertDatabaseMissing('exencion_cobro_planillas', ['cargo_id' => $cargoAnterior->id]);
    $this->assertDatabaseMissing('exencion_cobro_planillas', ['cargo_id' => $cargoVisitante->id]);
    $despues = filasMaterializacion();
    foreach ($antes as $tabla => $filas) {
        expect(array_values(array_filter($despues[$tabla], fn (array $fila): bool => in_array($fila['id'], array_column($filas, 'id'), true))))->toBe($filas);
    }
    expect($despues['pago_planillas'])->toBe($antes['pago_planillas'])
        ->and($despues['aplicacion_pago_planillas'])->toBe($antes['aplicacion_pago_planillas'])
        ->and($despues['exencion_cobro_planillas'])->toHaveCount(2)
        ->and($despues['historial_cobro_planillas'])->toHaveCount(count($antes['historial_cobro_planillas']) + 1);
    expect(HistorialCobroPlanilla::latest('id')->first()->accion)->toBe('generar_cargos');
})->with(['regular', 'interzonal', 'final'])->with([null, '', "  Acuerdo\n literal  "]);

it('conserva politica de otorgante degradado o eliminado con otro admin vigente', function (bool $borrar): void {
    $politica = app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->local->id, $this->contexto['edicion']->id);
    if ($borrar) {
        DB::table('users')->where('id', $this->admin->id)->delete();
    } else {
        User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);
    }
    $this->admin = crearAdminCargos('Otro Admin');
    ($this->crear)('regular');
    $exencion = ExencionCobroPlanilla::query()->sole();
    expect($exencion->politica_id)->toBe($politica->id)->and($exencion->usuario_id)->toBe($this->admin->id)
        ->and($exencion->politica->actor_nombre)->toBe('Admin Cobros')->and($exencion->actor_nombre)->toBe('Otro Admin');
})->with([true, false]);

it('rechaza enlaces de politica corruptos contra cargo persistido', function (string $caso): void {
    $padre = ($this->crear)('regular');
    $cargo = CargoPlanilla::query()->where('idEquipo', $this->local->id)->sole();
    $otro = crearContextoCargos('Otra Edicion');
    $equipo = $caso === 'otro equipo' ? $this->visitante : ($caso === 'otra edicion' ? crearEquipoCargos($otro, 'Otro Equipo') : $this->local);
    $politica = PoliticaExencionCobroPlanilla::create(['idEquipo' => $equipo->id, 'idEdicion' => $caso === 'otra edicion' ? $otro['edicion']->id : $this->contexto['edicion']->id, 'usuario_id' => $this->admin->id]);
    $valor = match ($caso) {
        'inexistente' => 2147483647, 'string' => (string) $politica->id, 'cero' => 0, 'fuera rango' => 2147483648, default => $politica->id,
    };
    $antes = filasMaterializacion();
    expect(fn () => ExencionCobroPlanilla::create(['cargo_id' => $cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 25000, 'politica_id' => $valor]))->toThrow(LogicException::class);
    expect(filasMaterializacion())->toBe($antes);
})->with(['otro equipo', 'otra edicion', 'inexistente', 'string', 'cero', 'fuera rango']);

it('acepta exencion concreta legacy sin politica y preserva enlace por FK', function (): void {
    ($this->crear)('regular');
    $cargo = CargoPlanilla::query()->where('idEquipo', $this->local->id)->sole();
    $legacy = ExencionCobroPlanilla::create(['cargo_id' => $cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 10000, 'politica_id' => null]);
    expect($legacy->fresh()->politica_id)->toBeNull()->and($legacy->politica)->toBeNull();
    $politica = app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->local->id, $this->contexto['edicion']->id);
    ($this->crear)('regular');
    $antes = filasMaterializacion();
    expect(fn () => DB::table('politica_exencion_cobro_planillas')->where('id', $politica->id)->delete())->toThrow(QueryException::class);
    expect(filasMaterializacion())->toBe($antes);
});

it('revierte padre cargos exenciones y evento ante veto o excepcion', function (string $tipo, bool $lanza): void {
    app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->local->id, $this->contexto['edicion']->id);
    $modelo = $tipo === 'exencion' ? ExencionCobroPlanilla::class : HistorialCobroPlanilla::class;
    $antes = filasMaterializacion();
    $dispatcher = $modelo::getEventDispatcher();
    $original = clone $dispatcher;
    $dispatcher->listen('eloquent.creating: '.$modelo, function () use ($lanza): bool {
        if ($lanza) {
            throw new RuntimeException('Fallo deliberado');
        }

        return false;
    });
    try {
        expect(fn () => ($this->crear)('final'))->toThrow(RuntimeException::class);
        expect(filasMaterializacion())->toBe($antes);
    } finally {
        $modelo::setEventDispatcher($original);
        app()->instance('events', $original);
    }
})->with(['exencion', 'historial'])->with([false, true]);
