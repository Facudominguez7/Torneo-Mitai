<?php

// "No vino": el equipo no pagó la planilla de ese partido, pero no queda debiendo.
// Es distinto de una exención: no suma importe eximido y se puede deshacer.

use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\Partido;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

function estadoAusencias(): array
{
    $estado = [];
    foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas', 'exencion_cobro_planillas', 'ausencia_cobro_planillas'] as $tabla) {
        $estado[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all();
    }

    return $estado;
}

function filaAusencia(CargoPlanilla $cargo): array
{
    return app(ConsultaCobrosService::class)->consultar([
        'idEdicion' => $cargo->idEdicion, 'estado' => 'todos', 'periodo' => 'todos',
    ])['cargos']->firstWhere('id', $cargo->id);
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Ausente');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival Ausente');
    $this->cargos = [];
    foreach ([5, 6, 7] as $dia) {
        $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
            'horario_datetime' => "2026-04-0{$dia} 10:00:00",
        ]));
        $this->cargos[$dia] = CargoPlanilla::create([
            'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
            'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
            'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
            'snapshot' => ['equipo' => ['nombre' => 'Equipo Ausente']],
        ]);
    }
    $this->cargo = $this->cargos[5];
    $this->admin = crearAdminCargos('Admin Ausencias');
    activarCobrosCargos();
    $this->servicio = app(AusenciasCobroService::class);
});

it('marca no vino dejando el cargo sin deuda, sin eximir y con historial', function (?string $motivo): void {
    $ausencia = $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id, $motivo);

    $fila = filaAusencia($this->cargo);
    expect($fila['deuda_ars'])->toBe(0)
        ->and($fila['estado'])->toBe('no_vino')
        ->and($fila['eximido_ars'])->toBe(0)
        ->and($fila['aplicado_ars'])->toBe(0)
        ->and($fila['precio_ars'])->toBe(25000)
        ->and($ausencia->fresh()->motivo)->toBe($motivo)
        ->and($ausencia->fresh()->revertido_at)->toBeNull()
        ->and(DB::table('exencion_cobro_planillas')->count())->toBe(0)
        ->and($this->cargo->fresh()->precio_original)->toBe(25000);

    $evento = HistorialCobroPlanilla::query()->where('accion', 'marcar_no_vino')->sole();
    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->actor_nombre)->toBe('Admin Ausencias')
        ->and($evento->valores_antes)->toMatchArray(['cargo_id' => $this->cargo->id, 'idEquipo' => $this->equipo->id, 'pendiente_ars' => 25000])
        ->and($evento->valores_despues)->toMatchArray(['ausencia_id' => $ausencia->id, 'pendiente_ars' => 0]);
})->with(['sin motivo' => [null], 'motivo literal' => ['  No se presentó  ']]);

it('saca al equipo que no vino de los deudores y de las exenciones', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);
    $consulta = app(ConsultaCobrosService::class);

    $resultado = $consulta->consultar(['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'todos', 'periodo' => 'todos']);
    $deudores = $consulta->consultar(['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'deudores', 'periodo' => 'todos'])['cargos'];
    $noVino = $consulta->consultar(['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'no_vino', 'periodo' => 'todos'])['cargos'];

    expect($resultado['resumen']['deuda_ars'])->toBe(50000)
        ->and($resultado['resumen']['exenciones'])->toBe(0)
        ->and($resultado['resumen']['eximido_ars'])->toBe(0)
        ->and($deudores->pluck('id')->all())->toBe([$this->cargos[6]->id, $this->cargos[7]->id])
        ->and($noVino->pluck('id')->all())->toBe([$this->cargo->id])
        ->and($consulta->deudoresPorEquipo($this->contexto['edicion']->id)->first())->toMatchArray(['deuda_ars' => 50000, 'cargos' => 2]);
});

it('deshace no vino devolviendo la deuda y registrando la reversion', function (): void {
    $ausencia = $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);

    $this->servicio->deshacerNoVino($this->admin, $this->equipo->id, $this->cargo->id);

    $fila = filaAusencia($this->cargo);
    $ausencia->refresh();
    expect($fila['deuda_ars'])->toBe(25000)
        ->and($fila['estado'])->toBe('pendiente')
        ->and($ausencia->revertido_at)->not->toBeNull()
        ->and($ausencia->revertido_por)->toBe($this->admin->id);

    $evento = HistorialCobroPlanilla::query()->where('accion', 'deshacer_no_vino')->sole();
    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->valores_antes)->toMatchArray(['cargo_id' => $this->cargo->id, 'ausencia_id' => $ausencia->id, 'pendiente_ars' => 0])
        ->and($evento->valores_despues)->toMatchArray(['pendiente_ars' => 25000]);
});

it('permite volver a marcar no vino despues de deshacerlo', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);
    $this->servicio->deshacerNoVino($this->admin, $this->equipo->id, $this->cargo->id);

    $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);

    expect(filaAusencia($this->cargo)['estado'])->toBe('no_vino')
        ->and(AusenciaCobroPlanilla::query()->where('cargo_id', $this->cargo->id)->count())->toBe(2)
        ->and(AusenciaCobroPlanilla::query()->where('cargo_id', $this->cargo->id)->whereNull('revertido_at')->count())->toBe(1);
});

it('rechaza marcar no vino sin escribir nada', function (string $caso, string $mensaje): void {
    match ($caso) {
        'pagado' => app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid()),
        'exento' => app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id),
        'marcado' => $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id),
    };
    $antes = estadoAusencias();

    try {
        $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);
        $this->fail('Se esperaba un rechazo.');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors()['cargo'])->toBe([$mensaje]);
    }

    expect(estadoAusencias())->toBe($antes);
})->with([
    'con pagos aplicados' => ['pagado', 'El cargo ya tiene pagos: si pagó, el equipo vino.'],
    'exento' => ['exento', 'El cargo ya tiene una exención.'],
    'ya marcado' => ['marcado', 'El equipo ya figura como que no vino en este partido.'],
]);

it('rechaza deshacer un cargo que no esta marcado', function (): void {
    $antes = estadoAusencias();

    expect(fn () => $this->servicio->deshacerNoVino($this->admin, $this->equipo->id, $this->cargo->id))
        ->toThrow(ValidationException::class, 'El equipo no figura como que no vino en este partido.')
        ->and(estadoAusencias())->toBe($antes);
});

it('no acepta pagos ni exenciones mientras el equipo figura como que no vino', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);
    $antes = estadoAusencias();

    expect(fn () => app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 5000, 'efectivo', (string) Str::uuid()))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(ExencionesCobroService::class)->eximirCargo($this->admin, $this->equipo->id, $this->cargo->id))
        ->toThrow(ValidationException::class)
        ->and(estadoAusencias())->toBe($antes);
});

it('no aplica credito sobrante a un partido donde el equipo no vino', function (): void {
    $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);
    $clave = (string) Str::uuid();
    $pago = app(PagosCobroService::class)->registrarPago($this->admin, $this->cargos[6]->id, 50000, 'efectivo', $clave);

    app(CreditosCobroService::class)->aplicarCredito($this->admin, $pago->id, $this->cargos[6]->id, $clave);

    expect(filaAusencia($this->cargo))->toMatchArray(['aplicado_ars' => 0, 'deuda_ars' => 0, 'estado' => 'no_vino'])
        ->and(filaAusencia($this->cargos[7]))->toMatchArray(['aplicado_ars' => 25000, 'estado' => 'pagado']);
});

it('solo un administrador vigente marca o deshace no vino', function (string $rol): void {
    $usuario = crearUsuarioConRolCargos($rol);
    $antes = estadoAusencias();

    expect(fn () => $this->servicio->marcarNoVino($usuario, $this->equipo->id, $this->cargo->id))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->servicio->deshacerNoVino($usuario, $this->equipo->id, $this->cargo->id))->toThrow(AuthorizationException::class)
        ->and(estadoAusencias())->toBe($antes);
})->with(['regular', 'editor']);

it('exige cobros activados y el cargo del equipo indicado', function (): void {
    $antes = estadoAusencias();

    expect(fn () => $this->servicio->marcarNoVino($this->admin, $this->rival->id, $this->cargo->id))
        ->toThrow(ValidationException::class, 'El cargo no existe o no pertenece al equipo indicado.');

    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    expect(fn () => $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id))->toThrow(RuntimeException::class)
        ->and(estadoAusencias())->toBe($antes);
});

it('conserva las marcas de no vino para la historia', function (): void {
    $ausencia = $this->servicio->marcarNoVino($this->admin, $this->equipo->id, $this->cargo->id);

    expect(fn () => $ausencia->delete())->toThrow(LogicException::class)
        ->and(fn () => $ausencia->update(['motivo' => 'otro']))->toThrow(LogicException::class);

    $this->servicio->deshacerNoVino($this->admin, $this->equipo->id, $this->cargo->id);
    $revertida = $ausencia->fresh();
    expect(fn () => $revertida->forceFill(['revertido_at' => now()->addDay()])->save())->toThrow(LogicException::class);
});
