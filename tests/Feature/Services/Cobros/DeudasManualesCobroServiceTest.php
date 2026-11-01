<?php

use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\EquipoEdicion;
use App\Models\HistorialCobroPlanilla;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Services\Cobros\AusenciasCobroService;
use App\Services\Cobros\ConsultaCobrosService;
use App\Services\Cobros\CreditosCobroService;
use App\Services\Cobros\DeudasManualesCobroService;
use App\Services\Cobros\PagosCobroService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos('Edicion Deuda Manual');
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Deudor');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival Manual');
    $this->admin = crearAdminCargos('Admin Manual');
    activarCobrosCargos();
    fijarTarifaCargos(25000);
    $this->servicio = app(DeudasManualesCobroService::class);
    $this->edicionId = $this->contexto['edicion']->id;
    $this->hace10Dias = now()->subDays(10)->toDateString();
});

it('carga una deuda sin partido con importe, fecha y motivo y queda pendiente en la consulta', function () {
    $cargo = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, 'Planilla anterior a activar los cobros');

    expect($cargo->exists)->toBeTrue()
        ->and($cargo->tipo)->toBe(CargoPlanilla::TIPO_DEUDA_MANUAL)
        ->and($cargo->parent_type)->toBeNull()
        ->and($cargo->parent_id)->toBeNull()
        ->and((int) $cargo->idEquipo)->toBe($this->equipo->id)
        ->and((int) $cargo->idEdicion)->toBe($this->edicionId)
        ->and((int) $cargo->idCategoria)->toBe($this->contexto['categoria']->id)
        ->and($cargo->precio_original)->toBe(25000)
        ->and($cargo->snapshot)->toMatchArray([
            'origen' => 'manual', 'motivo' => 'Planilla anterior a activar los cobros', 'fecha_deuda' => $this->hace10Dias,
        ])
        ->and($cargo->snapshot['equipo']['nombre'])->toBe('Equipo Deudor')
        ->and($cargo->snapshot['categoria']['nombre'])->toBe('Sub 12 Cobros');

    $fila = app(ConsultaCobrosService::class)->consultar([
        'idEdicion' => $this->edicionId, 'idEquipo' => $this->equipo->id, 'estado' => 'deudores', 'periodo' => 'todos',
    ])['cargos']->sole();
    expect($fila)->toMatchArray([
        'id' => $cargo->id, 'deuda_ars' => 25000, 'estado' => 'pendiente', 'sin_partido' => true,
        'partido' => null, 'fecha' => $this->hace10Dias.' 00:00', 'categoria' => 'Sub 12 Cobros',
    ]);

    $evento = HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->sole();
    expect($evento->usuario_id)->toBe($this->admin->id)
        ->and($evento->valores_antes)->toMatchArray(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->edicionId])
        ->and($evento->valores_despues)->toMatchArray([
            'cargo_id' => $cargo->id, 'importe_ars' => 25000, 'fecha' => $this->hace10Dias, 'motivo' => 'Planilla anterior a activar los cobros',
        ]);
});

it('permite varias deudas manuales del mismo equipo y guarda el motivo vacío como nulo', function () {
    $primera = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, '   ');
    $segunda = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 18000, now()->toDateString(), null);

    expect(CargoPlanilla::query()->where('tipo', CargoPlanilla::TIPO_DEUDA_MANUAL)->count())->toBe(2)
        ->and($primera->snapshot['motivo'])->toBeNull()
        ->and($segunda->precio_original)->toBe(18000);
});

it('toma la categoría de la inscripción en la edición y no la de alta del equipo', function () {
    $anterior = crearContextoCargos('Edicion Anterior');
    $this->equipo->forceFill(['idCategoria' => $anterior['categoria']->id])->saveQuietly();

    $cargo = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null);

    expect((int) $cargo->idCategoria)->toBe($this->contexto['categoria']->id);
});

it('rechaza un equipo que no está inscripto en la edición o no tiene categoría', function () {
    $otraEdicion = crearContextoCargos('Otra Edicion');
    $ajeno = crearEquipoCargos($otraEdicion, 'Equipo Ajeno');
    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $ajeno->id, 25000, $this->hace10Dias, null))
        ->toThrow(ValidationException::class, 'El equipo no está inscripto en esta edición.');

    EquipoEdicion::query()->where('idEquipo', $this->equipo->id)->update(['idCategoria' => null]);
    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null))
        ->toThrow(ValidationException::class, 'El equipo no tiene categoría en esta edición.');
    expect(CargoPlanilla::query()->count())->toBe(0);
});

it('rechaza una fecha futura o inválida', function (string $fecha) {
    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $fecha, null))
        ->toThrow(ValidationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with(['mañana' => fn () => now()->addDay()->toDateString(), 'texto' => 'ayer', 'formato' => '01/10/2026']);

it('rechaza importes fuera de rango', function (mixed $importe) {
    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, $importe, $this->hace10Dias, null))
        ->toThrow(ValidationException::class, 'El importe debe ser un entero en ARS mayor a 0.');
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with([0, -100, 2147483648, '25000']);

it('bloquea la carga cuando el equipo tiene una exención de planillas en la edición', function () {
    PoliticaExencionCobroPlanilla::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->edicionId, 'usuario_id' => $this->admin->id]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null))
        ->toThrow(ValidationException::class, 'El equipo está exento de planillas en esta edición.');
    expect(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
});

it('rechaza la carga con los cobros desactivados', function () {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

    expect(fn () => $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null))
        ->toThrow(ValidationException::class, 'Los cobros de planillas no están activados.');
});

it('solo un administrador puede cargar deudas', function (string $rol) {
    $usuario = crearUsuarioConRolCargos($rol);

    expect(fn () => $this->servicio->cargarDeuda($usuario, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null))
        ->toThrow(AuthorizationException::class);
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with(['editor', 'regular']);

it('muestra la deuda en la franja de las planillas desde su fecha y en el ranking de deudores', function () {
    $cargo = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, 'Planilla vieja');
    $consulta = app(ConsultaCobrosService::class);

    $antes = $consulta->deudasParaPlanilla($this->edicionId, [$this->equipo->id], now()->subDays(11)->format('Y-m-d H:i:s'));
    $mismoDia = $consulta->deudasParaPlanilla($this->edicionId, [$this->equipo->id], $this->hace10Dias.' 20:00:00');

    expect($antes['deudas'])->toBeEmpty()
        ->and($mismoDia['deudas']->pluck('id')->all())->toBe([$cargo->id])
        ->and($mismoDia['deudas']->first())->toMatchArray(['manual' => true, 'sin_partido' => true, 'cancha' => null])
        ->and($consulta->deudoresPorEquipo($this->edicionId)->firstWhere('idEquipo', $this->equipo->id)['deuda_ars'] ?? null)->toBe(25000);
});

it('el saldo a favor paga la deuda manual en orden por su fecha', function () {
    $rivalB = crearEquipoCargos($this->contexto, 'Rival Credito');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rivalB, ['horario_datetime' => now()->subDays(3)->format('Y-m-d H:i:s')]));
    $conPago = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->edicionId, 'idCategoria' => $this->contexto['categoria']->id,
        'precio_original' => 10000, 'snapshot' => [],
    ]);
    $tardia = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 4000, now()->subDay()->toDateString(), null);
    $temprana = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 5000, $this->hace10Dias, null);
    $pago = (new PagosCobroService)->registrarPago($this->admin, $conPago->id, 17000, 'efectivo', '11111111-1111-4111-8111-111111111111');

    (new CreditosCobroService)->aplicarCredito($this->admin, $pago->id, $tardia->id, 'ca11a111-1111-41a1-8a1a-111111111111');

    $deudas = app(ConsultaCobrosService::class)->consultar([
        'idEdicion' => $this->edicionId, 'idEquipo' => $this->equipo->id, 'estado' => 'todos', 'periodo' => 'todos',
    ])['cargos']->pluck('deuda_ars', 'id');
    expect($deudas[$conPago->id])->toBe(0)
        ->and($deudas[$tardia->id])->toBe(0)
        ->and($deudas[$temprana->id])->toBe(2000);
});

it('no permite marcar "no vino" en una deuda sin partido', function () {
    $cargo = $this->servicio->cargarDeuda($this->admin, $this->edicionId, $this->equipo->id, 25000, $this->hace10Dias, null);

    expect(fn () => app(AusenciasCobroService::class)->marcarNoVino($this->admin, $this->equipo->id, $cargo->id))
        ->toThrow(ValidationException::class, 'Una deuda cargada a mano no tiene partido: no se puede marcar que el equipo no vino.');
    expect(AusenciaCobroPlanilla::query()->count())->toBe(0);
});

it('el modelo exige que la deuda manual no tenga partido y que los cargos de partido sí lo tengan', function () {
    $base = ['idEquipo' => $this->equipo->id, 'idEdicion' => $this->edicionId, 'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 100, 'snapshot' => []];
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival));

    expect(fn () => CargoPlanilla::create($base + ['tipo' => CargoPlanilla::TIPO_DEUDA_MANUAL, 'parent_type' => Partido::class, 'parent_id' => $partido->id]))
        ->toThrow(RuntimeException::class)
        ->and(fn () => CargoPlanilla::create($base + ['tipo' => CargoPlanilla::TIPO_PARTIDO]))
        ->toThrow(RuntimeException::class);
});
