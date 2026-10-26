<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\EquipoEdicion;
use App\Models\HistorialCobroPlanilla;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\ExencionesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

function estadoRegistroPolitica(): array
{
    $estado = [];
    foreach (['politica_exencion_cobro_planillas', 'historial_cobro_planillas', 'cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'exencion_cobro_planillas', 'cuenta_cobro_planillas', 'configuracion_cobro_planillas'] as $tabla) {
        $estado[$tabla] = DB::table($tabla)->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all();
    }

    return $estado;
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Registro');
    $this->admin = crearAdminCargos();
    activarCobrosCargos();
    $rival = crearEquipoCargos($this->contexto, 'Rival Registro');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rival));
    $this->cargo = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ]);
    app(PagosCobroService::class)->registrarPago($this->admin, $this->cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $this->registrar = fn () => app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->equipo->id, $this->contexto['edicion']->id);
});

it('registra politica auditada sin alterar finanzas y conserva motivo literal', function (?string $motivo): void {
    $this->travelTo(now()->startOfSecond());
    User::query()->whereKey($this->admin->id)->update(['name' => 'Admin vigente']);
    $antes = estadoRegistroPolitica();
    $politica = app(ExencionesCobroService::class)->registrarPolitica($this->admin, $this->equipo->id, $this->contexto['edicion']->id, $motivo)->fresh();
    expect($politica->idEquipo)->toBe($this->equipo->id)->and($politica->idEdicion)->toBe($this->contexto['edicion']->id)
        ->and($politica->usuario_id)->toBe($this->admin->id)->and($politica->actor_nombre)->toBe('Admin vigente')
        ->and($politica->motivo)->toBe($motivo)->and($politica->created_at->equalTo(now()))->toBeTrue();
    $despues = estadoRegistroPolitica();
    foreach ($antes as $tabla => $filas) {
        if (! in_array($tabla, ['politica_exencion_cobro_planillas', 'historial_cobro_planillas'], true)) {
            expect($despues[$tabla])->toBe($filas);
        }
    }
    expect(array_slice($despues['historial_cobro_planillas'], 0, count($antes['historial_cobro_planillas'])))->toBe($antes['historial_cobro_planillas']);
    $evento = HistorialCobroPlanilla::latest('id')->first();
    expect($despues['politica_exencion_cobro_planillas'])->toHaveCount(1)->and($despues['historial_cobro_planillas'])->toHaveCount(count($antes['historial_cobro_planillas']) + 1)
        ->and($evento->accion)->toBe('registrar_politica_exencion')->and($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->actor_nombre)->toBe('Admin vigente')->and($evento->created_at->equalTo(now()))->toBeTrue()
        ->and($evento->valores_antes)->toBe(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id])
        ->and($evento->valores_despues)->toBe(['motivo' => $motivo, 'politica_id' => $politica->id]);
    expect($this->registrar)->toThrow(ValidationException::class);
    expect(estadoRegistroPolitica())->toBe($despues);
})->with([null, '', "  Acuerdo\n literal  "]);

it('rechaza administrador no vigente y claves alteradas sin cambios', function (string $caso): void {
    if ($caso === 'nuevo') {
        $this->admin = User::factory()->make(['rol' => 'admin']);
    } elseif ($caso === 'clave') {
        $this->admin->id = crearAdminCargos()->id;
    } elseif ($caso === 'borrado') {
        $this->admin->delete();
    } else {
        User::query()->whereKey($this->admin->id)->update(['rol' => $caso]);
    }
    $antes = estadoRegistroPolitica();
    expect($this->registrar)->toThrow(AuthorizationException::class);
    expect(estadoRegistroPolitica())->toBe($antes);
})->with(['editor', 'regular', 'nuevo', 'clave', 'borrado']);

it('requiere configuracion existente activa sin crear ni activar', function (bool $ausente): void {
    if ($ausente) {
        ConfiguracionCobroPlanilla::query()->whereKey(1)->delete();
    } else {
        ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    }
    $antes = estadoRegistroPolitica();
    expect($this->registrar)->toThrow(RuntimeException::class);
    expect(estadoRegistroPolitica())->toBe($antes);
})->with([true, false]);

it('requiere identidad valida e inscripcion sin cambios', function (string $caso): void {
    $equipoId = $caso === 'equipo cero' ? 0 : ($caso === 'equipo ausente' ? 2147483647 : $this->equipo->id);
    $edicionId = $caso === 'edicion negativa' ? -1 : ($caso === 'edicion ausente' ? 2147483647 : $this->contexto['edicion']->id);
    if ($caso === 'sin inscripcion') {
        EquipoEdicion::query()->where('idEquipo', $equipoId)->where('idEdicion', $edicionId)->delete();
    }
    $antes = estadoRegistroPolitica();
    expect(fn () => app(ExencionesCobroService::class)->registrarPolitica($this->admin, $equipoId, $edicionId))->toThrow(ValidationException::class);
    expect(estadoRegistroPolitica())->toBe($antes);
})->with(['equipo cero', 'equipo ausente', 'edicion negativa', 'edicion ausente', 'sin inscripcion']);

it('revierte politica e historial ante veto o excepcion', function (string $tipo, bool $lanza): void {
    $modelo = $tipo === 'politica' ? PoliticaExencionCobroPlanilla::class : HistorialCobroPlanilla::class;
    $antes = estadoRegistroPolitica();
    $dispatcher = $modelo::getEventDispatcher();
    $original = clone $dispatcher;
    $dispatcher->listen('eloquent.creating: '.$modelo, function () use ($lanza): bool {
        if ($lanza) {
            throw new RuntimeException('Fallo deliberado');
        }

        return false;
    });
    try {
        expect($this->registrar)->toThrow(RuntimeException::class);
        expect(estadoRegistroPolitica())->toBe($antes);
    } finally {
        $modelo::setEventDispatcher($original);
        app()->instance('events', $original);
    }
})->with(['politica', 'historial'])->with([false, true]);
