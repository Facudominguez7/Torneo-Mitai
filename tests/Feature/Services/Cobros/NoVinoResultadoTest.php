<?php

// Marking "no vino" automatically loads a 3-0 win for the team that showed up,
// and undoing it reverts that result together with the standings.

use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\ReemplazoEquipo;
use App\Models\TablaPosicion;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\PartidoService;
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

it('carga 3 a 0 para el visitante cuando el local no vino', function (): void {
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
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([3, 0, 1])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(['ganado' => 1, 'puntos' => 3]);
});

it('revierte el 3 a 0 y las posiciones al deshacer no vino', function (): void {
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

it('deja el partido sin resultado si no vinieron los dos y aplica el 3 a 0 al deshacer uno', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->local->id, $this->cargoLocal->id);
    $this->servicio->marcarNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([null, null, 0])
        ->and(posicionNoVino($partido, $this->local->id))->toMatchArray(sinPartidosJugados())
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(sinPartidosJugados());

    $this->servicio->deshacerNoVino($this->admin, $this->visitante->id, $this->cargoVisitante->id);

    $partido = $this->partido->fresh();
    expect([$partido->golesEquipoLocal, $partido->golesEquipoVisitante, (int) $partido->jugado])->toBe([0, 3, 1])
        ->and(posicionNoVino($partido, $this->visitante->id))->toMatchArray(['jugado' => 1, 'ganado' => 1, 'puntos' => 3]);
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
