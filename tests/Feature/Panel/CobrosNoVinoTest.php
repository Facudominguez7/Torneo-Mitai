<?php

// "No vino" desde la planilla del partido y desde /Panel/cobros: el equipo no pagó
// pero no queda debiendo; se puede deshacer y la deuda vuelve.

use App\Models\AplicacionPagoPlanilla;
use App\Models\AusenciaCobroPlanilla;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->withSession(['_token' => 'cobros-no-vino'])->withHeaders(['X-CSRF-TOKEN' => 'cobros-no-vino']);
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo faltante');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival presente');
    $this->admin = crearAdminCargos();
    app(ConfiguracionCobroService::class)->actualizarTarifa($this->admin, 25000);
    app(ConfiguracionCobroService::class)->activar($this->admin);
    $this->partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => '2026-04-05 10:00:00',
    ]));
    $this->cargos = [];
    foreach ([$this->equipo, $this->rival] as $participante) {
        $this->cargos[$participante->id] = CargoPlanilla::create([
            'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $this->partido->id,
            'idEquipo' => $participante->id, 'idEdicion' => $this->contexto['edicion']->id,
            'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
            'snapshot' => ['equipo' => ['nombre' => $participante->nombre]],
        ]);
    }
    $this->cargo = $this->cargos[$this->equipo->id];
    $this->entrada = ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'cargo_id' => $this->cargo->id];
});

function pagarNoVino(CargoPlanilla $cargo, int $importe, int $usuarioId): void
{
    $cuenta = CuentaCobroPlanilla::query()->firstOrCreate(['idEquipo' => $cargo->idEquipo, 'idEdicion' => $cargo->idEdicion]);
    $pago = PagoPlanilla::create([
        'cuenta_id' => $cuenta->id, 'cargo_origen_id' => $cargo->id, 'usuario_id' => $usuarioId,
        'importe_ars' => $importe, 'medio' => 'efectivo', 'clave_idempotencia' => (string) Str::uuid(),
    ]);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $cargo->id, 'importe_ars' => $importe]);
}

function seccionCobroNoVino(object $test): string
{
    return $test->get(route('cobros.partido', [
        'idEdicion' => $test->contexto['edicion']->id, 'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $test->partido->id,
    ]))->assertOk()->getContent();
}

/** @return array<int, string> html de cada fila de cobro, por cargo */
function filasCobroNoVino(string $html): array
{
    return collect(array_slice(explode('data-planilla-cobro="', $html), 1))
        ->mapWithKeys(fn (string $fila): array => [(int) $fila => $fila])->all();
}

it('ofrece no vino en la planilla solo a quien debe sin haber pagado', function (): void {
    pagarNoVino($this->cargos[$this->rival->id], 10000, $this->admin->id);

    $html = $this->actingAs($this->admin)->get(route('planilla.show', [
        'partidoId' => $this->partido->id, 'idEdicion' => $this->partido->idEdicion, 'tipoPartido' => 'partido', 'horario' => 0,
    ]))->assertOk()->getContent();
    $filas = filasCobroNoVino(Str::between($html, 'data-cobros-grilla', 'Cargar resultado'));

    expect(substr_count($filas[$this->cargo->id], 'data-cobro="no_vino"'))->toBe(1)
        ->and($filas[$this->cargo->id])->toContain('>No vino</button>')
        ->and(substr_count($filas[$this->cargos[$this->rival->id]->id], 'data-cobro="no_vino"'))->toBe(0)
        ->and($html)->not->toContain('data-cobro="deshacer_no_vino"');
});

it('marca no vino y la planilla lo muestra sin deuda con boton para deshacer', function (): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/no-vino', $this->entrada + ['motivo' => 'No se presentó'])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Operación registrada.');

    $fila = filasCobroNoVino(seccionCobroNoVino($this))[$this->cargo->id];
    expect($fila)->toStartWith($this->cargo->id.'" data-deuda="0" data-estado="no_vino"')
        ->and($fila)->toContain('No vino</span>')
        ->and($fila)->toContain('data-cobro="deshacer_no_vino"')
        ->and($fila)->toContain('Deshacer no vino')
        ->and($fila)->not->toContain('data-cobro="pago"')
        ->and($fila)->not->toContain('data-cobro="exencion"')
        ->and(AusenciaCobroPlanilla::query()->sole()->motivo)->toBe('No se presentó');
});

it('deshace no vino y la planilla vuelve a pedir el pago', function (): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/no-vino', $this->entrada)->assertSuccessful();

    $this->postJson('/Panel/cobros/no-vino-deshacer', $this->entrada)->assertSuccessful();

    $fila = filasCobroNoVino(seccionCobroNoVino($this))[$this->cargo->id];
    expect($fila)->toStartWith($this->cargo->id.'" data-deuda="25000" data-estado="pendiente"')
        ->and($fila)->toContain('data-cobro="pago"')
        ->and($fila)->toContain('data-cobro="no_vino"')
        ->and(HistorialCobroPlanilla::query()->whereIn('accion', ['marcar_no_vino', 'deshacer_no_vino'])->orderBy('id')->pluck('accion')->all())
        ->toBe(['marcar_no_vino', 'deshacer_no_vino']);
});

it('rechaza no vino cuando el equipo ya pago', function (): void {
    pagarNoVino($this->cargo, 10000, $this->admin->id);

    $this->actingAs($this->admin)->postJson('/Panel/cobros/no-vino', $this->entrada)
        ->assertUnprocessable()
        ->assertJsonPath('errors.cargo.0', 'El cargo ya tiene pagos: si pagó, el equipo vino.');

    expect(AusenciaCobroPlanilla::query()->count())->toBe(0);
});

it('exige el cargo para marcar o deshacer no vino', function (string $operacion): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/'.$operacion, ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cargo_id' => 'Completá este campo.']);
})->with(['no-vino', 'no-vino-deshacer']);

it('no deja marcar no vino a quien no es administrador', function (string $operacion): void {
    $this->actingAs(crearUsuarioConRolCargos('regular'))->post('/Panel/cobros/'.$operacion, $this->entrada)->assertRedirect('/');

    expect(AusenciaCobroPlanilla::query()->count())->toBe(0);
})->with(['no-vino', 'no-vino-deshacer']);

it('lista a quien no vino en la impresion filtrada y lo saca de los deudores', function (): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/no-vino', $this->entrada)->assertSuccessful();

    $deudores = $this->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))->assertOk()->getContent();
    $todos = $this->get(route('cobros.imprimir', ['idEdicion' => $this->contexto['edicion']->id, 'periodo' => 'todos']))->assertOk()->getContent();
    $filtrado = $this->get(route('cobros.imprimir', ['idEdicion' => $this->contexto['edicion']->id, 'periodo' => 'todos', 'estado' => 'no_vino']))
        ->assertOk()->getContent();

    expect($todos)->toContain('No vino')
        ->and($todos)->not->toContain('No_vino')
        ->and($deudores)->toContain('data-cobros-deudor="'.$this->rival->id.'"')
        ->and($deudores)->not->toContain('data-cobros-deudor="'.$this->equipo->id.'"')
        ->and($filtrado)->toContain('Equipo Faltante')
        ->and($filtrado)->not->toContain('<strong>Rival Presente</strong>');
});

it('rechaza un estado de filtro desconocido', function (): void {
    $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'ausente']))
        ->assertSessionHasErrors('estado');
});
