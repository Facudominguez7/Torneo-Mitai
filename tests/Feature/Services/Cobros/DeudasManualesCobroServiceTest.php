<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\DeudasManualesCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos('Edicion Deuda Manual');
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Deudor');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival Manual');
    $this->admin = crearAdminCargos('Admin Manual');
    activarCobrosCargos();
    fijarTarifaCargos(25000);
    $this->partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => now()->subWeek()->setTime(14, 0)->format('Y-m-d H:i:s'),
        'cancha' => 2, 'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0, 'jugado' => 1,
    ]));
    $this->servicio = app(DeudasManualesCobroService::class);
});

it('carga una deuda manual atada a un partido jugado y queda como deuda pendiente del equipo', function () {
    $cargo = $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, 'Planilla anterior a activar los cobros');

    expect($cargo->exists)->toBeTrue()
        ->and($cargo->tipo)->toBe(CargoPlanilla::TIPO_PARTIDO)
        ->and((int) $cargo->parent_id)->toBe($this->partido->id)
        ->and((int) $cargo->idEquipo)->toBe($this->equipo->id)
        ->and((int) $cargo->idEdicion)->toBe($this->contexto['edicion']->id)
        ->and($cargo->precio_original)->toBe(25000)
        ->and($cargo->snapshot['origen'])->toBe('manual')
        ->and($cargo->snapshot['oponente']['nombre'])->toBe('Rival Manual')
        ->and($cargo->snapshot['motivo'])->toBe('Planilla anterior a activar los cobros');

    $fila = app(ConsultaCobrosService::class)->consultar([
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'estado' => 'deudores', 'periodo' => 'todos',
    ])['cargos']->first();
    expect($fila['id'])->toBe($cargo->id)
        ->and($fila['deuda_ars'])->toBe(25000)
        ->and($fila['estado'])->toBe('pendiente');

    $evento = HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->sole();
    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->valores_antes)->toMatchArray(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id])
        ->and($evento->valores_despues)->toMatchArray(['cargo_id' => $cargo->id, 'importe_ars' => 25000, 'motivo' => 'Planilla anterior a activar los cobros']);
});

it('permite cargar la deuda de una instancia final jugada', function () {
    $final = InstanciaFinal::create(crearDatosFinalCargos($this->contexto, $this->equipo, $this->rival, [
        'horario' => now()->subDays(2)->format('Y-m-d H:i:s'),
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0,
    ]));

    $cargo = $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_INSTANCIA_FINAL, $final->id, $this->rival->id, 30000, null);

    expect($cargo->parent_type)->toBe(InstanciaFinal::class)
        ->and((int) $cargo->idEquipo)->toBe($this->rival->id)
        ->and($cargo->precio_original)->toBe(30000);
});

it('rechaza cargar dos veces la deuda del mismo equipo en el mismo partido', function () {
    $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'El equipo ya tiene un cargo de planilla en este partido.');
    expect(CargoPlanilla::query()->count())->toBe(1);
});

it('rechaza un partido que todavia no se jugo', function () {
    $futuro = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => now()->addDay()->format('Y-m-d H:i:s'),
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0, 'jugado' => 1,
    ]));

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $futuro->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'Solo se puede cargar la deuda de un partido ya jugado.');
});

it('excluye un partido regular pasado con resultado precargado pero no jugado', function () {
    $this->partido->update(['jugado' => 0, 'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0]);

    expect($this->servicio->partidosJugados($this->contexto['edicion']->id, $this->equipo->id)->pluck('valor')->all())
        ->not->toContain(CargoPlanilla::TIPO_PARTIDO.'-'.$this->partido->id);
});

it('rechaza cargar deuda de un partido regular no jugado con resultado precargado', function () {
    $this->partido->update(['jugado' => 0, 'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
});

it('lista y permite cargar deuda de un partido regular marcado como jugado con resultado', function () {
    $this->partido->update(['jugado' => 1, 'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0]);

    expect($this->servicio->partidosJugados($this->contexto['edicion']->id, $this->equipo->id)->pluck('valor')->all())
        ->toContain(CargoPlanilla::TIPO_PARTIDO.'-'.$this->partido->id);
    $cargo = $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null);
    expect($cargo->exists)->toBeTrue();
});

it('excluye y rechaza partidos pasados sin resultado completo', function (string $tipo, ?int $local, ?int $visitante) {
    $datos = ['golesEquipoLocal' => $local, 'golesEquipoVisitante' => $visitante];
    $partido = $tipo === CargoPlanilla::TIPO_PARTIDO
        ? Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, $datos + ['horario_datetime' => now()->subDay(), 'jugado' => 1]))
        : InstanciaFinal::create(crearDatosFinalCargos($this->contexto, $this->equipo, $this->rival, $datos + ['horario' => now()->subDay()]));

    expect($this->servicio->partidosJugados($this->contexto['edicion']->id, $this->equipo->id)->pluck('valor')->all())
        ->not->toContain($tipo.'-'.$partido->id);
    expect(fn () => $this->servicio->cargarDeuda($this->admin, $tipo, $partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with([
    [CargoPlanilla::TIPO_PARTIDO, null, null],
    [CargoPlanilla::TIPO_PARTIDO, 0, null],
    [CargoPlanilla::TIPO_INSTANCIA_FINAL, null, null],
    [CargoPlanilla::TIPO_INSTANCIA_FINAL, null, 0],
]);

it('bloquea la deuda dudosa aun cuando el horario programado precede a la política', function () {
    PoliticaExencionCobroPlanilla::create([
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'usuario_id' => $this->admin->id,
    ]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'El equipo está exento de planillas en esta edición.');
    expect(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
});

it('rechaza cuando la política es anterior o simultánea al partido', function (int $segundos) {
    $instantePolitica = now()->subDays(2)->startOfSecond();
    Carbon::setTestNow($instantePolitica);
    try {
        PoliticaExencionCobroPlanilla::create([
            'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'usuario_id' => $this->admin->id,
        ]);
    } finally {
        Carbon::setTestNow();
    }
    $this->partido->update([
        'horario_datetime' => $instantePolitica->copy()->addSeconds($segundos),
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0,
    ]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with([0, 60]);

it('rechaza un tipo de partido desconocido', function () {
    expect(fn () => $this->servicio->cargarDeuda($this->admin, 'desconocido', $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'El partido no existe.');
    expect(CargoPlanilla::query()->count())->toBe(0);
});

it('rechaza un equipo que no jugo ese partido', function () {
    $otro = crearEquipoCargos($this->contexto, 'Equipo Ajeno');

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $otro->id, 25000, null))
        ->toThrow(ValidationException::class, 'El equipo no jugó ese partido.');
});

it('rechaza importes fuera de rango', function (mixed $importe) {
    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, $importe, null))
        ->toThrow(ValidationException::class, 'El importe debe ser un entero en ARS mayor a 0.');
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with([0, -100, 2147483648, '25000']);

it('rechaza a un equipo exento de planillas en la edicion', function () {
    PoliticaExencionCobroPlanilla::create([
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'usuario_id' => $this->admin->id,
    ]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'El equipo está exento de planillas en esta edición.');
});

it('rechaza la carga con los cobros desactivados', function () {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(ValidationException::class, 'Los cobros de planillas no están activados.');
});

it('solo un administrador puede cargar deudas', function (string $rol) {
    $usuario = crearUsuarioConRolCargos($rol);

    expect(fn () => $this->servicio->cargarDeuda($usuario, CargoPlanilla::TIPO_PARTIDO, $this->partido->id, $this->equipo->id, 25000, null))
        ->toThrow(AuthorizationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with(['editor', 'regular']);
