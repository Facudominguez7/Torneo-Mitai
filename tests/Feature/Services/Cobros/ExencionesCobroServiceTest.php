<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

function filasExenciones(string $tabla): array
{
    return DB::table($tabla)->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all();
}

function estadoExenciones(): array
{
    $estado = [];
    foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas', 'exencion_cobro_planillas', 'cuenta_cobro_planillas', 'configuracion_cobro_planillas'] as $tabla) {
        $estado[$tabla] = filasExenciones($tabla);
    }

    return $estado;
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Exencion');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival Exencion');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));
    $this->datos = [
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => ['nombre' => 'Historico'],
    ];
    $this->cargo = CargoPlanilla::create($this->datos);
    $this->admin = crearAdminCargos();
    activarCobrosCargos();
    $this->eximir = fn () => app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('exime solo el pendiente exacto preservando historia no vacia y motivo literal', function (bool $final, int $pagado, ?string $motivo): void {
    if ($final) {
        $padre = InstanciaFinal::create(crearDatosFinalCargos($this->contexto, $this->equipo, $this->rival));
        $this->datos = array_replace($this->datos, ['tipo' => CargoPlanilla::TIPO_INSTANCIA_FINAL, 'parent_type' => InstanciaFinal::class, 'parent_id' => $padre->id]);
        $this->cargo = CargoPlanilla::create($this->datos);
    }
    CargoPlanilla::create(array_replace($this->datos, ['idEquipo' => $this->rival->id]));
    $otro = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));
    CargoPlanilla::create(array_replace($this->datos, ['tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $otro->id]));
    if ($pagado > 0) {
        app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, $pagado, 'efectivo', (string) Str::uuid());
    }
    $antes = estadoExenciones();
    if ($pagado > 0) {
        foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'] as $tabla) {
            expect($antes[$tabla])->not->toBeEmpty();
        }
    }
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre vigente']);
    Carbon::setTestNow('2026-10-07 13:14:15');

    $exencion = app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id, $motivo)->fresh();

    expect($exencion->importe_ars)->toBe(25000 - $pagado)->and($exencion->motivo)->toBe($motivo)
        ->and($exencion->actor_nombre)->toBe('Nombre vigente')->and($exencion->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-07 13:14:15');
    foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'configuracion_cobro_planillas'] as $tabla) {
        expect(filasExenciones($tabla))->toBe($antes[$tabla]);
    }
    $historia = filasExenciones('historial_cobro_planillas');
    expect(array_slice($historia, 0, count($antes['historial_cobro_planillas'])))->toBe($antes['historial_cobro_planillas']);
    expect($historia)->toHaveCount(count($antes['historial_cobro_planillas']) + 1);
    $evento = HistorialCobroPlanilla::latest('id')->first();
    expect($evento->accion)->toBe('eximir_cargo')->and($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->actor_nombre)->toBe('Nombre vigente')->and($evento->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-07 13:14:15')
        ->and($evento->valores_antes)->toBe(['cargo_id' => $this->cargo->id, 'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'pendiente_ars' => 25000 - $pagado])
        ->and($evento->valores_despues)->toBe(['exencion_id' => $exencion->id, 'importe_ars' => 25000 - $pagado, 'pendiente_ars' => 0]);
    $this->assertDatabaseCount('exencion_cobro_planillas', 1);
    $despues = estadoExenciones();
    expect($this->eximir)->toThrow(ValidationException::class);
    expect(estadoExenciones())->toBe($despues);
})->with([false, true])->with([0, 10000])->with([null, '', "  Acuerdo\n literal  "]);

it('rechaza actores no vigentes sin cambios financieros', function (string $caso): void {
    if (in_array($caso, ['regular', 'editor'], true)) {
        $this->admin = crearUsuarioConRolCargos($caso);
    } elseif ($caso === 'degradado') {
        User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);
    } elseif ($caso === 'borrado') {
        $this->admin->delete();
    } elseif ($caso === 'inexistente') {
        $this->admin->setRawAttributes(['id' => 2147483647], true);
    } elseif ($caso === 'nuevo') {
        $this->admin = User::factory()->make(['rol' => 'admin']);
    } else {
        $this->admin->id = crearAdminCargos()->id;
    }
    $antes = estadoExenciones();
    expect($this->eximir)->toThrow(AuthorizationException::class);
    expect(estadoExenciones())->toBe($antes);
})->with(['regular', 'editor', 'degradado', 'borrado', 'inexistente', 'nuevo', 'clave alterada']);

it('rechaza identidad de cargo o equipo invalida', function (string $caso): void {
    $equipoId = match ($caso) {
        'equipo negativo' => -1, 'equipo cero' => 0, 'equipo inexistente' => 2147483647, 'rival' => $this->rival->id, default => $this->equipo->id
    };
    $cargoId = match ($caso) {
        'cargo negativo' => -1, 'cargo cero' => 0, 'cargo inexistente' => 2147483647, default => $this->cargo->id
    };
    $antes = estadoExenciones();
    expect(fn () => app(ExencionesCobroService::class)->eximirCargo($this->admin, $equipoId, $cargoId))->toThrow(ValidationException::class);
    expect(estadoExenciones())->toBe($antes);
})->with(['equipo negativo', 'equipo cero', 'equipo inexistente', 'rival', 'cargo negativo', 'cargo cero', 'cargo inexistente']);

it('rechaza saldo cero exenciones previas y deuda corrupta', function (string $caso): void {
    if ($caso === 'saldado' || $caso === 'corrupto') {
        app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 25000, 'efectivo', (string) Str::uuid());
    }
    if ($caso !== 'saldado') {
        ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => $caso === 'total' ? 25000 : 5000]);
    }
    $antes = estadoExenciones();
    expect($this->eximir)->toThrow($caso === 'corrupto' ? RuntimeException::class : ValidationException::class);
    expect(estadoExenciones())->toBe($antes);
})->with(['saldado', 'parcial E1', 'total', 'corrupto']);

it('falla cerrado ante aplicaciones corruptas sin exencion previa', function (int $importe): void {
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    DB::table('aplicacion_pago_planillas')->where('cargo_id', $this->cargo->id)->update(['importe_ars' => $importe]);
    $antes = estadoExenciones();
    expect($this->eximir)->toThrow(RuntimeException::class);
    expect(estadoExenciones())->toBe($antes);
})->with([-1, 25001]);

it('no crea ni activa configuracion ausente o inactiva tampoco en reintentos', function (string $caso): void {
    if ($caso === 'reintento') {
        ($this->eximir)();
    }
    if ($caso === 'ausente') {
        ConfiguracionCobroPlanilla::query()->whereKey(1)->delete();
    } else {
        ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    }
    $antes = estadoExenciones();
    expect($this->eximir)->toThrow(RuntimeException::class);
    expect(estadoExenciones())->toBe($antes);
})->with(['ausente', 'inactiva', 'reintento']);

it('revierte ambas inserciones y cuenta nueva ante veto o excepcion', function (string $tipo, bool $lanza): void {
    $modelo = $tipo === 'exencion' ? ExencionCobroPlanilla::class : HistorialCobroPlanilla::class;
    $antes = estadoExenciones();
    $nombre = 'eloquent.creating: '.$modelo;
    $dispatcher = $modelo::getEventDispatcher();
    $original = clone $dispatcher;
    $dispatcher->listen($nombre, function () use ($lanza): bool {
        if ($lanza) {
            throw new RuntimeException('Fallo deliberado');
        }

        return false;
    });
    try {
        expect($this->eximir)->toThrow(RuntimeException::class);
        expect(estadoExenciones())->toBe($antes);
    } finally {
        $modelo::setEventDispatcher($original);
        app()->instance('events', $original);
    }
})->with(['exencion', 'historial'])->with([false, true]);

it('revierte motivo que supera TEXT sin truncarlo', function (): void {
    $antes = estadoExenciones();
    expect(fn () => app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id, str_repeat('x', 65536)))->toThrow(QueryException::class);
    expect(estadoExenciones())->toBe($antes);
});

it('adquiere bloqueos FOR UPDATE en orden compatible', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        ($this->eximir)();
        $bloqueos = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn (string $sql): bool => str_contains(strtolower($sql), 'for update')));
        $tablas = array_map(function (string $sql): string {
            preg_match('/from `([^`]+)`/', $sql, $coincidencia);

            return $coincidencia[1];
        }, $bloqueos);
        expect($tablas)->toBe(['users', 'configuracion_cobro_planillas', 'cuenta_cobro_planillas', 'cuenta_cobro_planillas', 'cargo_planillas', 'aplicacion_pago_planillas', 'exencion_cobro_planillas']);
    } finally {
        DB::disableQueryLog();
    }
});
