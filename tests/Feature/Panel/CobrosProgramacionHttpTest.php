<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function () {
    $this->admin = crearAdminCargos();
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Local');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante');
    $this->actingAs($this->admin)->withSession(['_token' => 'cobros-http'])
        ->withHeaders(['X-CSRF-TOKEN' => 'cobros-http']);
});

function datosAltaCobrosHttp(object $test, string $tipo): array
{
    if ($tipo === 'final') {
        return crearDatosFinalCargos($test->contexto, $test->local, $test->visitante, [
            'horario' => '2026-04-05T10:00', 'cancha' => '-1',
        ]);
    }

    $datos = crearDatosPartidoCargos($test->contexto, $test->local, $test->visitante, [
        'cancha' => '02', 'golesEquipoLocal' => '2', 'golesEquipoVisitante' => '0', 'jugado' => '-1',
    ]);
    if ($tipo === 'interzonal') {
        unset($datos['idGrupo']);
        $datos['horario_datetime'] = null;
    }

    return $datos;
}

function rutaAltaCobrosHttp(string $tipo): string
{
    return route(match ($tipo) {
        'final' => 'instancia_final.store',
        'interzonal' => 'partido.store-interzonal',
        default => 'partido.store',
    });
}

it('conserva el contrato HTTP legacy sin activar cobros', function (string $tipo) {
    PoliticaExencionCobroPlanilla::query()->create([
        'idEquipo' => $this->local->id, 'idEdicion' => $this->contexto['edicion']->id,
        'usuario_id' => $this->admin->id, 'motivo' => 'No materializar inactivo',
    ]);
    $datos = datosAltaCobrosHttp($this, $tipo);
    $respuesta = $this->post(rutaAltaCobrosHttp($tipo), $datos);
    $parametros = ['idEdicion' => $this->contexto['edicion']->id];
    if ($tipo !== 'final') {
        $parametros += ['idCategoria' => $this->contexto['categoria']->id];
        if ($tipo === 'regular') {
            $parametros['idGrupo'] = $this->contexto['grupo']->id;
        }
        $parametros['idFecha'] = $this->contexto['fecha']->id;
    }
    $respuesta->assertSessionHasNoErrors()
        ->assertRedirect(route($tipo === 'final' ? 'instancia_final.index' : 'partido.index', $parametros))
        ->assertSessionHas('status', $tipo === 'interzonal' ? 'Partido interzonal creado con éxito.' : 'Partido creado con éxito.');
    $padre = $tipo === 'final' ? InstanciaFinal::query()->sole() : Partido::query()->sole();
    expect((string) $padre->cancha)->toBe($tipo === 'final' ? '-1' : '2')
        ->and(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0)
        ->and(ExencionCobroPlanilla::query()->count())->toBe(0)
        ->and(ConfiguracionCobroPlanilla::query()->findOrFail(1)->activado_at)->toBeNull();
    if ($tipo === 'regular') {
        expect((int) $padre->jugado)->toBe(-1)->and((int) $padre->golesEquipoLocal)->toBe(2);
    }
    if ($tipo === 'interzonal') {
        expect($padre->idGrupo)->toBeNull()->and($padre->horario_datetime)->toBeNull()->and((bool) $padre->es_interzonal)->toBeTrue();
    }
})->with(['regular', 'interzonal', 'final']);

it('genera dos cargos y materializa solo la política del equipo al crear por HTTP', function (string $tipo) {
    activarCobrosCargos();
    fijarTarifaCargos(3500);
    $politica = PoliticaExencionCobroPlanilla::query()->create([
        'idEquipo' => $this->local->id, 'idEdicion' => $this->contexto['edicion']->id,
        'usuario_id' => $this->admin->id, 'motivo' => '  Acuerdo literal  ',
    ]);
    $datos = datosAltaCobrosHttp($this, $tipo);
    $this->post(rutaAltaCobrosHttp($tipo), $datos)->assertSessionHasNoErrors()->assertRedirect();
    $padre = $tipo === 'final' ? InstanciaFinal::query()->sole() : Partido::query()->sole();
    $cargos = CargoPlanilla::query()->orderBy('id')->get();
    expect($cargos)->toHaveCount(2)
        ->and($cargos->pluck('precio_original')->all())->toBe([3500, 3500])
        ->and($cargos->pluck('idEquipo')->map(fn ($id) => (int) $id)->all())->toBe([$this->local->id, $this->visitante->id]);
    foreach ($cargos as $cargo) {
        expect($cargo->parent->is($padre))->toBeTrue()->and((string) $cargo->snapshot['cancha'])->toBe($datos['cancha']);
    }
    $exencion = ExencionCobroPlanilla::query()->sole();
    expect((int) $exencion->politica_id)->toBe($politica->id)
        ->and((int) $exencion->cargo_id)->toBe($cargos->first()->id)
        ->and($exencion->importe_ars)->toBe(3500)->and($exencion->motivo)->toBe('  Acuerdo literal  ');
    $evento = HistorialCobroPlanilla::query()->sole();
    expect($evento->accion)->toBe('generar_cargos')->and((int) $evento->usuario_id)->toBe($this->admin->id);
})->with(['regular', 'interzonal', 'final']);

it('revierte el alta HTTP si falla la auditoría financiera', function (string $tipo) {
    activarCobrosCargos();
    $despachador = HistorialCobroPlanilla::getEventDispatcher();
    HistorialCobroPlanilla::setEventDispatcher(clone $despachador);
    PoliticaExencionCobroPlanilla::query()->create([
        'idEquipo' => $this->local->id, 'idEdicion' => $this->contexto['edicion']->id,
        'usuario_id' => $this->admin->id, 'motivo' => 'Rollback',
    ]);
    $auditoriaAlcanzada = false;
    HistorialCobroPlanilla::creating(function () use (&$auditoriaAlcanzada) {
        $auditoriaAlcanzada = true;
        expect(CargoPlanilla::query()->count())->toBe(2)
            ->and(ExencionCobroPlanilla::query()->count())->toBe(1)
            ->and(Partido::query()->count() + InstanciaFinal::query()->count())->toBe(1);
        throw new RuntimeException('Fallo sintético de auditoría');
    });
    try {
        $this->post(rutaAltaCobrosHttp($tipo), datosAltaCobrosHttp($this, $tipo))->assertServerError();
        expect($auditoriaAlcanzada)->toBeTrue()
            ->and(Partido::query()->count())->toBe(0)->and(InstanciaFinal::query()->count())->toBe(0)
            ->and(CargoPlanilla::query()->count())->toBe(0)->and(ExencionCobroPlanilla::query()->count())->toBe(0)
            ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
    } finally {
        HistorialCobroPlanilla::setEventDispatcher($despachador);
    }
})->with(['regular', 'interzonal', 'final']);

it('rechaza un equipo repetido en finales sin generar cargos', function () {
    activarCobrosCargos();
    $datos = datosAltaCobrosHttp($this, 'final');
    $datos['idEquipoVisitante'] = $datos['idEquipoLocal'];
    $this->post(rutaAltaCobrosHttp('final'), $datos)->assertSessionHasErrors('idEquipoVisitante');
    expect(InstanciaFinal::query()->count())->toBe(0)->and(CargoPlanilla::query()->count())->toBe(0);
});

it('impide altas HTTP a usuarios sin rol administrador', function (string $tipo) {
    activarCobrosCargos();
    $this->actingAs(crearUsuarioConRolCargos('regular'))
        ->post(rutaAltaCobrosHttp($tipo), datosAltaCobrosHttp($this, $tipo))->assertRedirect('/');
    expect(Partido::query()->count())->toBe(0)->and(InstanciaFinal::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['regular', 'interzonal', 'final']);

it('permite interzonales sin horario ni cancha por HTTP', function (bool $activo) {
    if ($activo) {
        activarCobrosCargos();
    }
    $datos = datosAltaCobrosHttp($this, 'interzonal');
    $datos['cancha'] = null;
    $this->post(rutaAltaCobrosHttp('interzonal'), $datos)->assertSessionHasNoErrors()->assertRedirect();
    $partido = Partido::query()->sole();
    expect($partido->cancha)->toBeNull()->and($partido->horario_datetime)->toBeNull()
        ->and(CargoPlanilla::query()->count())->toBe($activo ? 2 : 0);
    if ($activo) {
        expect(CargoPlanilla::query()->first()->snapshot['fecha_partido'])->toBeNull();
    }
})->with([false, true]);

it('conserva la aceptación HTTP de cancha negativa', function (string $tipo, bool $activo) {
    if ($activo) {
        activarCobrosCargos();
    }
    $datos = datosAltaCobrosHttp($this, $tipo);
    $datos['cancha'] = '-1';
    $this->post(rutaAltaCobrosHttp($tipo), $datos)->assertSessionHasNoErrors()->assertRedirect();
    $padre = $tipo === 'final' ? InstanciaFinal::query()->sole() : Partido::query()->sole();
    expect((string) $padre->cancha)->toBe('-1')->and(CargoPlanilla::query()->count())->toBe($activo ? 2 : 0);
})->with(['regular', 'interzonal', 'final'])->with([false, true]);

it('conserva el rechazo de etiquetas no persistibles sin efectos financieros', function (string $tipo, bool $activo) {
    if ($activo) {
        activarCobrosCargos();
    }
    $datos = datosAltaCobrosHttp($this, $tipo);
    $datos['cancha'] = 'Cancha Norte 02';
    $respuesta = $this->post(rutaAltaCobrosHttp($tipo), $datos);
    if ($tipo === 'final') {
        $respuesta->assertRedirect()->assertSessionHasErrors('cancha');
    } else {
        $respuesta->assertServerError();
    }
    expect(Partido::query()->count())->toBe(0)->and(InstanciaFinal::query()->count())->toBe(0)
        ->and(CargoPlanilla::query()->count())->toBe(0)->and(ExencionCobroPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['regular', 'interzonal', 'final'])->with([false, true]);
