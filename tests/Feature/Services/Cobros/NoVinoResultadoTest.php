<?php

// Marking "no vino" automatically loads a 3-0 win for the team that showed up,
// and undoing it reverts that result together with the standings.
// The integral reset (restablecer) removes score, player goals and referee
// reports, keeps the automatic 3-0 suppressed until it is explicitly
// reactivated, and preserves planillas and cobro records.

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\EquipoGrupo;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\ReemplazoEquipo;
use App\Models\TablaPosicion;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use App\Services\PartidoService;
use App\Services\ReemplazadorEquipo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

function cargoNoVino(Partido|InstanciaFinal $partido, $equipo, array $contexto): CargoPlanilla
{
    return CargoPlanilla::create([
        'tipo' => $partido instanceof Partido ? CargoPlanilla::TIPO_PARTIDO : CargoPlanilla::TIPO_INSTANCIA_FINAL,
        'parent_type' => $partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => 25000,
        'snapshot' => ['equipo' => ['nombre' => $equipo->nombre]],
    ]);
}

function posicionNoVino(Partido $partido, int $equipoId): ?array
{
    return TablaPosicion::query()->where('idGrupo', $partido->idGrupo)->where('idEquipo', $equipoId)
        ->where('idEdicion', $partido->idEdicion)->first()
        ?->only(['jugado', 'ganado', 'empatado', 'perdido', 'golesFavor', 'golesContra', 'puntos']);
}

function sinPartidosJugados(): array
{
    return ['jugado' => 0, 'ganado' => 0, 'empatado' => 0, 'perdido' => 0, 'golesFavor' => 0, 'golesContra' => 0, 'puntos' => 0];
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Local No Vino');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante No Vino');
    $this->partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->local, $this->visitante));
    $this->cargoLocal = cargoNoVino($this->partido, $this->local, $this->contexto);
    $this->cargoVisitante = cargoNoVino($this->partido, $this->visitante, $this->contexto);
    $this->admin = crearAdminCargos('Admin No Vino');
    activarCobrosCargos();
    $this->servicio = app(AusenciasCobroService::class);
});

it('conserva la ausencia y los cargos sin cobrar al reemplazante de un partido pendiente', function (): void {
    $nuevo = crearEquipoCargos($this->contexto, 'Reemplazante sin cargo');
    EquipoGrupo::create(['idGrupo' => $this->contexto['grupo']->id, 'idEquipo' => $this->local->id]);
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);

    app(ReemplazadorEquipo::class)->reemplazar(
        $this->contexto['edicion']->id, $this->contexto['categoria']->id, $this->local->id, $nuevo->id, null,
    );
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    $partido = $this->partido->fresh();
    $cargos = app(ConsultaCobrosService::class)->cargosDePartidos(CargoPlanilla::TIPO_PARTIDO, [$partido->id]);
    expect([(int) $partido->idEquipoLocal, $partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([$nuevo->id, null, null, 0])
        ->and($cargos->count())->toBe(2)
        ->and($cargos->has($partido->id.'-'.$nuevo->id))->toBeFalse()
        ->and($cargos->get($partido->id.'-'.$this->local->id)['estado'])->toBe('no_vino')
        ->and(AusenciaCobroPlanilla::query()->vigentes()->count())->toBe(2);

    $this->servicio->deshacerNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    expect((int) $this->partido->fresh()->jugado)->toBe(0)
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue();
});

it('ignora la ausencia historica del reemplazado al cambiar la ausencia del rival', function (): void {
    $nuevo = crearEquipoCargos($this->contexto, 'Reemplazante');
    EquipoGrupo::create(['idGrupo' => $this->contexto['grupo']->id, 'idEquipo' => $this->local->id]);

    // El reemplazante participó anteriormente y ya tenía un cargo cubierto para este partido.
    $editarLocal = fn (int $equipoId) => $this->actingAs($this->admin)->patch(route('partido.update', $this->partido->id), [
        'idFechas' => $this->contexto['fecha']->id, 'idEquipoLocal' => $equipoId,
        'idEquipoVisitante' => $this->visitante->id, 'horario_datetime' => '2026-04-05 10:00:00', 'cancha' => '1',
    ])->assertSessionHasNoErrors();
    $editarLocal($nuevo->id);
    $cargoNuevo = cargoNoVino($this->partido, $nuevo, $this->contexto);
    app(PagosCobroService::class)->registrarPago($this->admin, $cargoNuevo->id, 25000, 'efectivo', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    $editarLocal($this->local->id);
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(ReemplazadorEquipo::class)->reemplazar(
        $this->contexto['edicion']->id, $this->contexto['categoria']->id, $this->local->id, $nuevo->id, null,
    );
    expect((int) $this->partido->fresh()->idEquipoLocal)->toBe($nuevo->id)
        ->and(CargoPlanilla::query()->where('parent_type', Partido::class)->where('parent_id', $this->partido->id)->count())->toBe(3)
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue();

    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->ganador_por_no_vino])->toBe([3, 0, 1, $nuevo->id])
        ->and(posicionNoVino($partido, $nuevo->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3])
        ->and(AusenciaCobroPlanilla::query()->vigentes()->count())->toBe(2);

    $this->servicio->deshacerNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $nuevo->id))->toMatchArray(sinPartidosJugados())
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue();
});

it('espera el pago completo posterior para aplicar el resultado y las posiciones', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $this->visitante->id))->toBeNull();

    $pagos = app(PagosCobroService::class);
    $pagos->registrarPago($this->admin, $this->cargoVisitante->id, 10000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    expect((int) $this->partido->fresh()->jugado)->toBe(0);
    $pagos->registrarPago($this->admin, $this->cargoVisitante->id, 15000, 'efectivo', '22222222-2222-4222-8222-222222222222');
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([0, 3, 1])
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3]);
});

it('conserva la exencion del ausente y espera que el rival cubra su planilla', function (string $forma): void {
    $exenciones = app(ExencionesCobroService::class);
    $exenciones->eximirEdicion($this->admin, $this->local->id, $this->contexto['edicion']->id);
    $exencion = ExencionCobroPlanilla::query()->where('cargo_id', $this->cargoLocal->id)->sole();
    $politicaId = $exencion->politica_id;

    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id, 'No se presentó');
    expect([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante, (int) $this->partido->fresh()->jugado])->toBe([null, null, 0]);

    if ($forma === 'pago') {
        app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    } else {
        $exenciones->eximirCargo($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    }
    expect([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3])
        ->and(PlanillaJugador::query()->count())->toBe(0);

    $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $fila = app(ConsultaCobrosService::class)->consultar(['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'todos', 'periodo' => 'todos'])['cargos']->firstWhere('id', $this->cargoLocal->id);
    expect($fila)->toMatchArray(['estado' => 'exento', 'deuda_ars' => 0, 'eximido_ars' => 25000])
        ->and(ExencionCobroPlanilla::query()->where('cargo_id', $this->cargoLocal->id)->sole()->politica_id)->toBe($politicaId)
        ->and([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([null, null]);
})->with(['pago', 'exencion']);

it('aplica el resultado con exencion antes o despues de la ausencia', function (bool $antes): void {
    $eximir = fn () => app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->visitante->id, $this->cargoVisitante->id);
    if ($antes) {
        $eximir();
    }
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    if ($antes === false) {
        expect((int) $this->partido->fresh()->jugado)->toBe(0);
        $eximir();
    }
    expect([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3])
        ->and(posicionNoVino($this->partido, $this->visitante->id))->toMatchArray(['ganado' => 1, 'puntos' => 3])
        ->and((int) ExencionCobroPlanilla::query()->where('cargo_id', $this->cargoVisitante->id)->value('importe_ars'))->toBe(25000)
        ->and(PagoPlanilla::query()->count())->toBe(0);
})->with(['antes' => true, 'despues' => false]);

it('aplica el resultado con credito antes o despues de la ausencia', function (bool $antes): void {
    $rival = crearEquipoCargos($this->contexto, 'Rival origen credito');
    $origenPartido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->visitante, $rival));
    $origen = cargoNoVino($origenPartido, $this->visitante, $this->contexto);
    $pago = app(PagosCobroService::class)->registrarPago($this->admin, $origen->id, 50000, 'efectivo', '33333333-3333-4333-8333-333333333333');
    $aplicar = fn () => app(CreditosCobroService::class)->aplicarCredito($this->admin, $pago->id, $this->cargoVisitante->id, '44444444-4444-4444-8444-444444444444');
    if ($antes) {
        $aplicar();
    }
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    if ($antes === false) {
        expect((int) $this->partido->fresh()->jugado)->toBe(0);
        $aplicar();
    }
    expect([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3])
        ->and(posicionNoVino($this->partido, $this->visitante->id))->toMatchArray(['ganado' => 1, 'puntos' => 3])
        ->and((int) AplicacionPagoPlanilla::query()->where('cargo_id', $this->cargoVisitante->id)->sum('importe_ars'))->toBe(25000)
        ->and(PagoPlanilla::query()->count())->toBe(1)
        ->and($pago->fresh()->importe_ars)->toBe(50000);
})->with(['antes' => true, 'despues' => false]);

it('aplica la exencion de edicion tras la ausencia sin crear pagos', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $resultado = app(ExencionesCobroService::class)->eximirEdicion($this->admin, $this->visitante->id, $this->contexto['edicion']->id);
    expect($resultado['eximido_ars'])->toBe(25000)
        ->and([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3])
        ->and(PagoPlanilla::query()->count())->toBe(0);
});

it('rechaza cambiar equipos con ausencia vigente y conserva partido y cargos', function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $reemplazo = crearEquipoCargos($this->contexto, 'Nuevo visitante');
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $respuesta = $this->actingAs($this->admin)->patch(route('partido.update', $this->partido->id), [
        'idFechas' => $this->contexto['fecha']->id,
        'idEquipoLocal' => $this->local->id,
        'idEquipoVisitante' => $reemplazo->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => '1',
    ]);
    $respuesta->assertSessionHasErrors([
        'idEquipoLocal' => 'Los equipos no pueden modificarse porque el partido tiene una ausencia vigente.',
        'idEquipoVisitante' => 'Los equipos no pueden modificarse porque el partido tiene una ausencia vigente.',
    ]);
    $partido = $this->partido->fresh();
    $cargos = app(ConsultaCobrosService::class)->cargosDePartidos(CargoPlanilla::TIPO_PARTIDO, [$partido->id]);
    expect([(int) $partido->idEquipoLocal, (int) $partido->idEquipoVisitante, $partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([$this->local->id, $this->visitante->id, null, null, 0])
        ->and($cargos->count())->toBe(2)
        ->and($cargos->get($partido->id.'-'.$this->visitante->id)['estado'])->toBe('no_vino')
        ->and($cargos->get($partido->id.'-'.$this->local->id)['deuda_ars'])->toBe(25000)
        ->and($cargos->has($partido->id.'-'.$reemplazo->id))->toBeFalse()
        ->and(AusenciaCobroPlanilla::query()->vigentes()->count())->toBe(1);

    $this->actingAs($this->admin)->patch(route('partido.update', $partido->id), [
        'idFechas' => $this->contexto['fecha']->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => '2',
    ])->assertSessionHasNoErrors();
    expect((int) $partido->fresh()->cancha)->toBe(2);
});

it('permite editar equipos cuando no hay ausencia ni resultado', function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $reemplazo = crearEquipoCargos($this->contexto, 'Visitante nuevo');
    $respuesta = $this->actingAs($this->admin)->patch(route('partido.update', $this->partido->id), [
        'idFechas' => $this->contexto['fecha']->id,
        'idEquipoLocal' => $this->local->id,
        'idEquipoVisitante' => $reemplazo->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => '1',
    ]);
    $respuesta->assertSessionHasNoErrors();
    expect((int) $this->partido->fresh()->idEquipoVisitante)->toBe($reemplazo->id);
});

it('rechaza marcar ausencia de un equipo que dejo de participar', function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $nuevo = crearEquipoCargos($this->contexto, 'Equipo nuevo');
    $this->actingAs($this->admin)->patch(route('partido.update', $this->partido->id), [
        'idFechas' => $this->contexto['fecha']->id,
        'idEquipoLocal' => $this->local->id,
        'idEquipoVisitante' => $nuevo->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => '1',
    ])->assertSessionHasNoErrors();

    expect(fn () => $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id))
        ->toThrow(ValidationException::class, 'El equipo ya no participa en este partido.');
    expect(AusenciaCobroPlanilla::query()->vigentes()->count())->toBe(0)
        ->and((int) $this->partido->fresh()->idEquipoVisitante)->toBe($nuevo->id);
});

it('mantiene suprimido el resultado automatico al cubrir la planilla', function (): void {
    $this->partido->update(['resultado_no_vino_suprimido' => true]);
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '55555555-5555-4555-8555-555555555555');

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->resultado_no_vino_suprimido])->toBe([null, null, 0, true])
        ->and(posicionNoVino($partido, $this->visitante->id))->toBeNull()
        ->and(PagoPlanilla::query()->count())->toBe(1);
});

it('revierte el pago cuando falla la actualizacion del resultado', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    Partido::updating(function (): void {
        throw new RuntimeException('Fallo simulado del resultado');
    });

    try {
        expect(fn () => app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '66666666-6666-4666-8666-666666666666'))
            ->toThrow(RuntimeException::class, 'Fallo simulado del resultado');
    } finally {
        Partido::flushEventListeners();
    }

    $partido = $this->partido->fresh();
    expect(PagoPlanilla::query()->count())->toBe(0)
        ->and(AplicacionPagoPlanilla::query()->count())->toBe(0)
        ->and([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $this->visitante->id))->toBeNull()
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue();
});

it('carga 3 a 0 para el visitante cuando el local no vino', function (): void {
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([0, 3, 1])
        ->and($partido->ganador_por_no_vino)->toBe($this->visitante->id)
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'golesFavor' => 3, 'golesContra' => 0, 'puntos' => 3])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(['jugado' => 1, 'perdido' => 1, 'golesFavor' => 0, 'golesContra' => 3, 'puntos' => 0])
        ->and($this->visitante->fresh()->only(['victorias', 'puntos', 'golesFavor']))->toBe(['victorias' => 1, 'puntos' => 3, 'golesFavor' => 3])
        ->and($this->local->fresh()->only(['derrotas', 'golesContra']))->toBe(['derrotas' => 1, 'golesContra' => 3]);
});

it('carga 3 a 0 para el local cuando el visitante no vino', function (): void {
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoLocal->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([3, 0, 1])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(['ganado' => 1, 'puntos' => 3]);
});

it('revierte el 3 a 0 y las posiciones al deshacer no vino', function (): void {
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(sinPartidosJugados())
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(sinPartidosJugados())
        ->and($this->visitante->fresh()->only(['victorias', 'puntos', 'partidos_jugados']))->toBe(['victorias' => 0, 'puntos' => 0, 'partidos_jugados' => 0]);
});

it('rechaza no vino si el partido ya tiene un resultado cargado a mano, sin escribir nada', function (): void {
    app(PartidoService::class)->actualizarResultado($this->partido, 2, 1);
    $antes = posicionNoVino($this->partido, $this->local->id);

    expect(fn () => $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id))
        ->toThrow(ValidationException::class, 'El partido ya tiene un resultado cargado: corregilo antes de marcar que el equipo no vino.');

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante])->toBe([2, 1])
        ->and(AusenciaCobroPlanilla::query()->count())->toBe(0)
        ->and(posicionNoVino($partido, $this->local->id))->toBe($antes);
});

it('deja el partido sin resultado si no vinieron los dos y al deshacer uno sin pagar', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $this->local->id))->toBeNull()
        ->and(posicionNoVino($partido, $this->visitante->id))->toBeNull();

    $this->servicio->deshacerNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0]);
});

it('conserva un resultado corregido a mano al deshacer no vino', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(PartidoService::class)->actualizarResultado($this->partido->fresh(), 1, 1);

    $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([1, 1, 1])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(['jugado' => 1, 'empatado' => 1, 'puntos' => 1]);
});

it('no carga resultado automatico en instancias finales', function (): void {
    $final = InstanciaFinal::create(crearDatosFinalCargos($this->contexto, $this->local, $this->visitante));
    $cargo = cargoNoVino($final, $this->local, $this->contexto);

    $this->servicio->marcarNoVino($this->admin, $this->local->id, $cargo->id);

    $final = $final->fresh();
    expect([$final->golesEquipoLocal, $final->golesEquipoVisitante])->toBe([null, null])
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $cargo->id)->exists())->toBeTrue();
});

it('rechaza no vino sobre un 3 a 0 cargado a mano y lo conserva', function (string $ausente): void {
    app(PartidoService::class)->actualizarResultado($this->partido, 3, 0);
    [$equipo, $cargo] = $ausente === 'local' ? [$this->local, $this->cargoLocal] : [$this->visitante, $this->cargoVisitante];

    expect(fn () => $this->servicio->marcarNoVino($this->admin, $equipo->id, $cargo->id))
        ->toThrow(ValidationException::class, 'El partido ya tiene un resultado cargado: corregilo antes de marcar que el equipo no vino.');

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, $partido->ganador_por_no_vino])->toBe([3, 0, null])
        ->and(AusenciaCobroPlanilla::query()->count())->toBe(0);
})->with(['perdedor' => 'visitante', 'ganador' => 'local']);

it('conserva un 0 a 3 cargado a mano despues del no vino al deshacerlo', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(PartidoService::class)->actualizarResultado($this->partido->fresh(), 0, 3);

    $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->ganador_por_no_vino])->toBe([0, 3, 1, null])
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3]);
});

it('informa el motivo si el 3 a 0 no se puede revertir por un equipo reemplazado', function (): void {
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    ReemplazoEquipo::create([
        'idEdicion' => $this->contexto['edicion']->id, 'idCategoria' => $this->contexto['categoria']->id,
        'idGrupo' => $this->contexto['grupo']->id, 'idEquipoReemplazado' => $this->local->id,
        'idEquipoReemplazante' => crearEquipoCargos($this->contexto, 'Reemplazante')->id,
    ]);

    expect(fn () => $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id))
        ->toThrow(ValidationException::class, 'Este partido no se puede modificar ni eliminar');

    expect(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue()
        ->and([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3]);
});

it('el restablecimiento integral borra el 3 a 0 automatico y queda suprimido ante pagos posteriores', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $pagos = app(PagosCobroService::class);
    $pagos->registrarPago($this->admin, $this->cargoVisitante->id, 10000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    $pagos->registrarPago($this->admin, $this->cargoVisitante->id, 15000, 'efectivo', '22222222-2222-4222-8222-222222222222');
    expect([$this->partido->fresh()->golesEquipoLocal, $this->partido->fresh()->golesEquipoVisitante])->toBe([0, 3]);

    // Dos aplicaciones consecutivas dejan el mismo estado (idempotencia).
    $servicioPartidos = app(PartidoService::class);
    $servicioPartidos->restablecerResultado($this->partido);
    $servicioPartidos->restablecerResultado($this->partido);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and($partido->ganador_por_no_vino)->toBeNull()
        ->and($partido->resultado_no_vino_suprimido)->toBeTrue()
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(sinPartidosJugados())
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(sinPartidosJugados())
        ->and($this->visitante->fresh()->only(['victorias', 'puntos', 'partidos_jugados']))->toBe(['victorias' => 0, 'puntos' => 0, 'partidos_jugados' => 0]);

    // El cargo de una ausencia no tiene deuda y no admite pagos anticipados.
    expect(fn () => $pagos->registrarPago($this->admin, $this->cargoLocal->id, 25000, 'efectivo', '33333333-3333-4333-8333-333333333333'))
        ->toThrow(ValidationException::class, 'El cargo no registra deuda pendiente; no se admiten anticipos.');
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and($partido->resultado_no_vino_suprimido)->toBeTrue()
        ->and(PagoPlanilla::query()->count())->toBe(2)
        ->and(CargoPlanilla::query()->where('parent_id', $partido->id)->where('parent_type', Partido::class)->count())->toBe(2)
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue();

    $this->servicio->deshacerNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->resultado_no_vino_suprimido])->toBe([null, null, 0, true])
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeFalse();

    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->resultado_no_vino_suprimido])->toBe([null, null, 0, true])
        ->and($partido->ganador_por_no_vino)->toBeNull()
        ->and(AusenciaCobroPlanilla::query()->vigentes()->where('cargo_id', $this->cargoLocal->id)->exists())->toBeTrue()
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(sinPartidosJugados())
        ->and(PagoPlanilla::query()->count())->toBe(2);

    $this->servicio->reactivarResultadoAutomatico($this->admin, (int) $partido->id);
    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->resultado_no_vino_suprimido])->toBe([0, 3, 1, false])
        ->and($partido->ganador_por_no_vino)->toBe((int) $this->visitante->id)
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3]);
});

it('la reactivacion expresa vuelve a aplicar el 3 a 0 automatico', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    app(PartidoService::class)->restablecerResultado($this->partido);
    expect((bool) $this->partido->fresh()->resultado_no_vino_suprimido)->toBeTrue();

    $this->servicio->reactivarResultadoAutomatico($this->admin, (int) $this->partido->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([0, 3, 1])
        ->and($partido->ganador_por_no_vino)->toBe((int) $this->visitante->id)
        ->and($partido->resultado_no_vino_suprimido)->toBeFalse()
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3])
        ->and(HistorialCobroPlanilla::query()->where('accion', 'reactivar_resultado_no_vino')->count())->toBe(1);
});

it('la reactivacion no pisa un resultado cargado a mano', function (): void {
    $this->partido->update(['resultado_no_vino_suprimido' => true]);
    app(PartidoService::class)->actualizarResultado($this->partido, 2, 1);
    $antes = posicionNoVino($this->partido, $this->local->id);

    $this->servicio->reactivarResultadoAutomatico($this->admin, (int) $this->partido->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado, $partido->ganador_por_no_vino])->toBe([2, 1, 1, null])
        ->and($partido->resultado_no_vino_suprimido)->toBeFalse()
        ->and(posicionNoVino($partido, $this->local->id))->toBe($antes)
        ->and(AusenciaCobroPlanilla::query()->count())->toBe(0);
});

it('solo un administrador puede reactivar el resultado suprimido', function (): void {
    $this->partido->update(['resultado_no_vino_suprimido' => true]);
    $colaborador = crearUsuarioConRolCargos('regular');

    expect(fn () => $this->servicio->reactivarResultadoAutomatico($colaborador, (int) $this->partido->id))
        ->toThrow(AuthorizationException::class);

    expect((bool) $this->partido->fresh()->resultado_no_vino_suprimido)->toBeTrue()
        ->and(HistorialCobroPlanilla::query()->where('accion', 'reactivar_resultado_no_vino')->count())->toBe(0);
});

it('el restablecimiento integral conserva la asistencia de planillas y el registro de cobros', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargoVisitante->id, 25000, 'efectivo', '11111111-1111-4111-8111-111111111111');
    Jugador::create(['dni' => '30999888', 'nombre' => 'Delantero', 'apellido' => 'Visitante', 'goles_totales' => 2]);
    PlanillaJugador::create([
        'partido_id' => $this->partido->id, 'partido_type' => Partido::class,
        'dni_jugador' => '30999888', 'idEquipo' => $this->visitante->id,
        'idCategoria' => $this->contexto['categoria']->id, 'idEdicion' => $this->contexto['edicion']->id,
        'numero_camiseta' => 9, 'goles' => 2, 'asistio' => true,
    ]);

    app(PartidoService::class)->restablecerResultado($this->partido);

    $planilla = PlanillaJugador::query()->where('partido_id', $this->partido->id)->where('partido_type', Partido::class)->first();
    expect($planilla)->not->toBeNull()
        ->and((int) $planilla->goles)->toBe(0)
        ->and((bool) $planilla->asistio)->toBeTrue()
        ->and((int) Jugador::query()->where('dni', '30999888')->value('goles_totales'))->toBe(0)
        ->and(PagoPlanilla::query()->count())->toBe(1);
});
