<?php

use App\Models\AusenciaCobroPlanilla;
use App\Models\Campeon;
use App\Models\CargoPlanilla;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\EquipoGrupo;
use App\Models\ExencionCobroPlanilla;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaPosicion;
use App\Services\Cobros\ProgramacionCobroService;
use App\Services\MoverInstanciaFinalAFechaService;
use App\Services\PartidoService;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    $this->withoutVite();
    // Las ediciones 1 a 3 usan un cálculo legacy de goles en contra.
    foreach (range(1, 3) as $indice) {
        Edicion::create(['nombre' => 'Edicion previa '.$indice]);
    }
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Tacuari Mover');
    $this->visitante = crearEquipoCargos($this->contexto, 'Peques Mover');
    foreach ([$this->local, $this->visitante] as $equipo) {
        EquipoGrupo::create(['idEquipo' => $equipo->id, 'idGrupo' => $this->contexto['grupo']->id]);
    }
    $this->admin = crearAdminCargos();
    activarCobrosCargos();

    $this->crearFinal = fn (array $datos = []): InstanciaFinal => app(ProgramacionCobroService::class)
        ->crearInstanciaFinal($this->admin, crearDatosFinalCargos($this->contexto, $this->local, $this->visitante, ['cancha' => '2', ...$datos]));

    $this->cargarResultado = function (InstanciaFinal $final, int $local = 3, int $visitante = 1): InstanciaFinal {
        $final->forceFill(['golesEquipoLocal' => $local, 'golesEquipoVisitante' => $visitante])->save();
        app(PartidoService::class)->actualizarHistorialInstanciasFinales($final, $local, $visitante);
        app(PartidoService::class)->actualizarGolesContraEquipoEdicion($this->local->id, $final->idEdicion, 0);
        app(PartidoService::class)->actualizarGolesContraEquipoEdicion($this->visitante->id, $final->idEdicion, 0);

        return $final->refresh();
    };

    $this->mover = fn (InstanciaFinal $final, bool $interzonal = false): Partido => app(MoverInstanciaFinalAFechaService::class)
        ->mover($this->admin, $final, $this->contexto['fecha']->id, $interzonal);
});

it('mueve la final a la fecha conservando planilla, reportes y cargos', function (): void {
    $final = ($this->cargarResultado)(($this->crearFinal)());
    $token = $final->public_token;
    $idsCargos = CargoPlanilla::query()->where('parent_type', InstanciaFinal::class)->where('parent_id', $final->id)->orderBy('id')->pluck('id')->all();
    Jugador::create(['dni' => '40510091', 'nombre' => 'Juan', 'apellido' => 'Mover', 'goles_totales' => 0, 'partidos_totales' => 0, 'tarjetas_rojas_totales' => 0]);
    $planilla = PlanillaJugador::create([
        'partido_id' => $final->id, 'partido_type' => InstanciaFinal::class, 'dni_jugador' => '40510091',
        'idEquipo' => $this->local->id, 'idEdicion' => $final->idEdicion, 'idCategoria' => $final->idCategoria,
        'numero_camiseta' => 10, 'fecha_nacimiento' => '2010-02-01', 'goles' => 3, 'asistio' => true, 'tarjeta_roja' => false,
    ]);
    $reporte = GolArbitroReportado::create([
        'partido_id' => $final->id, 'partido_type' => InstanciaFinal::class, 'idEdicion' => $final->idEdicion,
        'idEquipo' => $this->local->id, 'idPlanillaJugador' => $planilla->id, 'numero_camiseta' => 10, 'goles_reportados' => 3,
    ]);

    $partido = ($this->mover)($final);

    expect(InstanciaFinal::query()->find($final->id))->toBeNull()
        ->and((int) $partido->idFechas)->toBe($this->contexto['fecha']->id)
        ->and((int) $partido->idGrupo)->toBe($this->contexto['grupo']->id)
        ->and((int) $partido->cancha)->toBe(2)
        ->and((int) $partido->jugado)->toBe(1)
        ->and($partido->public_token)->toBe($token)
        ->and((int) $planilla->refresh()->partido_id)->toBe($partido->id)
        ->and($planilla->partido_type)->toBe(Partido::class)
        ->and((int) $planilla->goles)->toBe(3)
        ->and((int) $reporte->refresh()->partido_id)->toBe($partido->id)
        ->and($reporte->partido_type)->toBe(Partido::class);

    $cargos = CargoPlanilla::query()->whereKey($idsCargos)->orderBy('id')->get();
    expect($cargos)->toHaveCount(2)
        ->and($cargos->pluck('tipo')->unique()->all())->toBe([CargoPlanilla::TIPO_PARTIDO])
        ->and($cargos->pluck('parent_type')->unique()->all())->toBe([Partido::class])
        ->and($cargos->pluck('parent_id')->map(fn ($id) => (int) $id)->unique()->all())->toBe([$partido->id])
        ->and($cargos[0]->snapshot['partido']['id'])->toBe($partido->id)
        ->and($cargos[0]->snapshot['partido']['id_fecha'])->toBe($this->contexto['fecha']->id)
        ->and($cargos[0]->snapshot['partido']['es_interzonal'])->toBeFalse()
        ->and(ExencionCobroPlanilla::query()->whereIn('cargo_id', $idsCargos)->exists())->toBeFalse()
        ->and(HistorialCobroPlanilla::query()->where('accion', 'mover_final_a_fecha')->count())->toBe(1);
});

it('suma el resultado a posiciones sin duplicar el historial ni los goles en contra', function (): void {
    $final = ($this->cargarResultado)(($this->crearFinal)());
    $historialAntes = $this->local->refresh()->only(['partidos_jugados', 'victorias', 'puntos', 'golesFavor', 'golesContra']);

    ($this->mover)($final);

    $fila = TablaPosicion::query()->where('idEquipo', $this->local->id)->where('idGrupo', $this->contexto['grupo']->id)->firstOrFail();
    $inscripcion = $this->local->refresh()->only(['partidos_jugados', 'victorias', 'puntos', 'golesFavor', 'golesContra']);

    expect((int) $fila->puntos)->toBe(3)
        ->and((int) $fila->jugado)->toBe(1)
        ->and((int) $fila->golesFavor)->toBe(3)
        ->and((int) $fila->golesContra)->toBe(1)
        ->and($inscripcion)->toBe($historialAntes)
        ->and((int) $this->local->equipoEdiciones()->where('idEdicion', $final->idEdicion)->value('golesContra'))->toBe(1);
});

it('mueve una final sin resultado sin tocar posiciones', function (): void {
    $partido = ($this->mover)(($this->crearFinal)());

    expect((int) $partido->jugado)->toBe(0)
        ->and($partido->golesEquipoLocal)->toBeNull()
        ->and(TablaPosicion::query()->where('idEquipo', $this->local->id)->exists())->toBeFalse();
});

it('exige confirmar interzonal cuando los equipos son de grupos distintos', function (): void {
    $otroGrupo = Grupo::create(['idCategoria' => $this->contexto['categoria']->id, 'idEdicion' => $this->contexto['edicion']->id, 'nombre' => 'Grupo B']);
    EquipoGrupo::query()->where('idEquipo', $this->visitante->id)->update(['idGrupo' => $otroGrupo->id]);
    $final = ($this->crearFinal)();

    expect(fn () => ($this->mover)($final))->toThrow(DomainException::class, 'interzonal');
    expect(InstanciaFinal::query()->find($final->id))->not->toBeNull();

    $partido = ($this->mover)($final, true);

    expect($partido->idGrupo)->toBeNull()->and((bool) $partido->es_interzonal)->toBeTrue();
});

it('bloquea el movimiento y no cambia nada', function (Closure $preparar, string $mensaje): void {
    $final = ($this->crearFinal)();
    $preparar->call($this, $final);
    $cantidadPartidos = Partido::query()->count();

    expect(fn () => ($this->mover)($final->refresh()))->toThrow(DomainException::class, $mensaje);

    expect(InstanciaFinal::query()->find($final->id))->not->toBeNull()
        ->and(Partido::query()->count())->toBe($cantidadPartidos)
        ->and(CargoPlanilla::query()->where('parent_type', InstanciaFinal::class)->where('parent_id', $final->id)->count())->toBe(2);
})->with([
    'penales' => [function (InstanciaFinal $final): void {
        $final->forceFill(['golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1, 'penalesEquipoLocal' => 4, 'penalesEquipoVisitante' => 3])->save();
    }, 'penales'],
    'edición histórica' => [function (InstanciaFinal $final): void {
        Edicion::query()->find(2) ?? Edicion::query()->forceCreate(['id' => 2, 'nombre' => 'Edicion legacy']);
        $final->forceFill(['idEdicion' => 2])->save();
    }, 'edición histórica'],
    'un solo gol cargado' => [function (InstanciaFinal $final): void {
        $final->forceFill(['golesEquipoLocal' => 1])->save();
    }, 'resultado incompleto'],
    'cancha no numérica' => [function (InstanciaFinal $final): void {
        $final->forceFill(['cancha' => 'Central'])->save();
    }, 'cancha'],
    'partido duplicado en la fecha' => [function (InstanciaFinal $final): void {
        Partido::create(crearDatosPartidoCargos($this->contexto, $this->visitante, $this->local));
    }, 'ya tiene un partido'],
    'equipo sin grupo' => [function (InstanciaFinal $final): void {
        EquipoGrupo::query()->where('idEquipo', $this->visitante->id)->delete();
    }, 'grupo'],
    'ausencia sin resultado' => [function (InstanciaFinal $final): void {
        $cargo = CargoPlanilla::query()->where('parent_type', InstanciaFinal::class)->where('parent_id', $final->id)->firstOrFail();
        AusenciaCobroPlanilla::create(['cargo_id' => $cargo->id, 'usuario_id' => $this->admin->id, 'motivo' => 'No vino']);
    }, 'no vino'],
    'campeón confirmado' => [function (InstanciaFinal $final): void {
        $copa = Copa::create(['nombre' => 'Copa Mover']);
        $final->forceFill(['idCopa' => $copa->id])->save();
        Campeon::create(['idEquipo' => $this->local->id, 'idCopa' => $copa->id, 'idCategoria' => $final->idCategoria, 'idEdicion' => $final->idEdicion]);
    }, 'podio'],
]);

it('detecta el resultado global guardado en columnas legacy', function (string $columna): void {
    $final = new InstanciaFinal;
    $final->setRawAttributes([$columna => '4-2']);

    expect(app(MoverInstanciaFinalAFechaService::class)->tieneResultadoGlobal($final))->toBeTrue()
        ->and(app(MoverInstanciaFinalAFechaService::class)->tieneResultadoGlobal(new InstanciaFinal))->toBeFalse();
})->with(['resultadoGlobal', 'resultadoGlobalLocal', 'resultadoGlobalVisitante']);

it('rechaza una fecha de otra categoría', function (): void {
    $final = ($this->crearFinal)();
    $otra = crearContextoCargos('Otra edición');
    $fechaAjena = Fecha::create(['idEdicion' => $otra['edicion']->id, 'idCategoria' => $otra['categoria']->id, 'nombre' => 'Fecha ajena']);

    expect(fn () => app(MoverInstanciaFinalAFechaService::class)->mover($this->admin, $final, $fechaAjena->id))
        ->toThrow(DomainException::class, 'fecha');
});

it('muestra el formulario y mueve desde el panel', function (): void {
    $final = ($this->crearFinal)();
    $parametros = ['instancia_final' => $final->id, 'idEdicion' => $final->idEdicion];

    $this->actingAs($this->admin)->get(route('instancia_final.mover-fecha', $parametros))
        ->assertOk()
        ->assertSee('Mover a una fecha')
        ->assertSee($this->contexto['fecha']->nombre);

    $this->actingAs($this->admin)
        ->withSession(['_token' => 'token-csrf-mover'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-mover'])
        ->post(route('instancia_final.mover-fecha.store', $parametros), ['idFechas' => $this->contexto['fecha']->id])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(InstanciaFinal::query()->find($final->id))->toBeNull();
});

it('impide mover a quien no es administrador', function (): void {
    $final = ($this->crearFinal)();

    $this->actingAs(crearUsuarioConRolCargos('editor'))
        ->withSession(['_token' => 'token-csrf-mover'])
        ->withHeaders(['X-CSRF-TOKEN' => 'token-csrf-mover'])
        ->post(route('instancia_final.mover-fecha.store', ['instancia_final' => $final->id]), ['idFechas' => $this->contexto['fecha']->id]);

    expect(InstanciaFinal::query()->find($final->id))->not->toBeNull();
});
