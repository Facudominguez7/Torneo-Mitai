<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\User;
use App\Services\Cobros\ConsultaCobrosService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo consulta');
    $rival = crearEquipoCargos($this->contexto, 'Rival consulta');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rival));
    $this->cargo = CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
        'snapshot' => ['equipo' => ['nombre' => 'Nombre histórico'], 'fecha_partido' => '2026-06-14 10:00:00'],
    ]);
    $this->admin = crearAdminCargos();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('busca por nombre vigente o histórico y cuenta exenciones sin convertirlas en ingresos', function (): void {
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 25000]);
    foreach (['Equipo consulta', 'nombre HISTÓRICO'] as $nombre) {
        $this->actingAs($this->admin)->get(route('cobros.index', [
            'idEdicion' => $this->contexto['edicion']->id, 'estado' => 'todos', 'buscar' => $nombre,
        ]))->assertOk()->assertViewHas('resumen', fn (array $resumen): bool => $resumen['exenciones'] === 1 && $resumen['ingresos_ars'] === 0);
    }
    expect(app(ConsultaCobrosService::class)->consultar(['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'todos', 'buscar' => 'inexistente'])['cargos'])->toHaveCount(0);
});

it('consulta deuda histórica sin crear estado financiero', function (): void {
    $resultado = app(ConsultaCobrosService::class)->consultar(['idEdicion' => $this->contexto['edicion']->id]);
    expect($resultado['resumen']['deuda_ars'])->toBe(25000)
        ->and($resultado['cargos'])->toHaveCount(1)
        ->and($resultado['cargos'][0]['equipo'])->toBe('Nombre histórico');
    foreach (['cuenta_cobro_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas'] as $tabla) {
        $this->assertDatabaseCount($tabla, 0);
    }
});

it('protege consulta e impresión para administradores', function (): void {
    foreach (['/Panel/cobros', '/Panel/cobros/imprimir'] as $url) {
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(crearUsuarioConRolCargos('regular'))->get($url)->assertRedirect('/');
        $this->actingAs($this->admin)->get($url.'?idEdicion='.$this->contexto['edicion']->id)->assertOk();
        auth()->forgetGuards();
    }
});

function pagoConsulta(CargoPlanilla $cargo, User $admin, int $importe, string $medio = 'efectivo'): PagoPlanilla
{
    $cuenta = CuentaCobroPlanilla::firstOrCreate(['idEquipo' => $cargo->idEquipo, 'idEdicion' => $cargo->idEdicion]);

    return PagoPlanilla::create(['cuenta_id' => $cuenta->id, 'cargo_origen_id' => $cargo->id,
        'usuario_id' => $admin->id, 'importe_ars' => $importe, 'medio' => $medio, 'clave_idempotencia' => (string) Str::uuid()]);
}

it('separa ingreso real crédito y aplicaciones sin duplicar un pago distribuido', function (): void {
    $partido = $this->cargo->parent->replicate(['public_token']);
    $partido->save();
    $otro = CargoPlanilla::create(array_merge($this->cargo->only(['tipo', 'parent_type', 'idEquipo', 'idEdicion', 'idCategoria']), ['parent_id' => $partido->id, 'precio_original' => 25000, 'snapshot' => []]));
    Carbon::setTestNow('2026-06-10 10:00:00');
    $pago = pagoConsulta($this->cargo, $this->admin, 60000);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 25000]);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $otro->id, 'importe_ars' => 10000]);
    ExencionCobroPlanilla::create(['cargo_id' => $otro->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 5000]);
    Carbon::setTestNow('2026-06-15 12:00:00');
    $filtros = ['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'todos'];
    $resultado = app(ConsultaCobrosService::class)->consultar($filtros);
    expect($resultado['resumen'])->toMatchArray(['ingresos_ars' => 60000, 'credito_ars' => 25000,
        'recaudacion_asociada_ars' => 35000, 'deuda_ars' => 10000, 'eximido_ars' => 5000, 'deudores' => 1]);
    $fuera = app(ConsultaCobrosService::class)->consultar($filtros + ['recibido_desde' => '2026-06-11']);
    expect($fuera['resumen']['ingresos_ars'])->toBe(0)->and($fuera['resumen']['credito_ars'])->toBe(25000);
    $parcial = app(ConsultaCobrosService::class)->consultar(array_merge($filtros, ['estado' => 'parcial']));
    expect($parcial['cargos'])->toHaveCount(1)->and($parcial['resumen']['ingresos_ars'])->toBe(60000);
});

it('permite futuros y recupera filtros en lista e impresión sin escrituras', function (): void {
    $this->cargo->parent->update(['horario_datetime' => '2026-07-01 10:00:00']);
    $filtros = ['idEdicion' => $this->contexto['edicion']->id];
    expect(app(ConsultaCobrosService::class)->consultar($filtros)['cargos'])->toHaveCount(0);
    $filtros += ['periodo' => 'futuros', 'estado' => 'pendiente', 'fecha' => '2026-07-01',
        'idCategoria' => $this->contexto['categoria']->id, 'idEquipo' => $this->equipo->id,
        'tipo' => $this->cargo->tipo, 'partido' => $this->cargo->parent_id];
    $this->actingAs($this->admin);
    $escrituras = [];
    DB::listen(function (QueryExecuted $query) use (&$escrituras): void {
        if (preg_match('/^\\s*(insert|update|delete|replace)\\b/i', $query->sql)) {
            $escrituras[] = $query->sql;
        }
    });
    foreach (['cobros.index', 'cobros.imprimir'] as $ruta) {
        $this->get(route($ruta, $filtros))->assertOk()->assertSee('Nombre histórico')
            ->assertViewHas('filtros', fn (array $valor): bool => $valor['periodo'] === 'futuros')
            ->assertViewHas('cargos', fn ($filas): bool => count($filas) === 1);
    }
    expect($escrituras)->toBe([]);
});

it('acepta contexto de partido sin cargos y rechaza partido ajeno o tipo inválido', function (): void {
    $sinCargos = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, crearEquipoCargos($this->contexto, 'Nuevo rival')));
    $ajeno = crearContextoCargos('Partido ajeno');
    $partidoAjeno = Partido::create(crearDatosPartidoCargos($ajeno, crearEquipoCargos($ajeno, 'Local ajeno'), crearEquipoCargos($ajeno, 'Rival ajeno')));
    $this->actingAs($this->admin);
    foreach (['cobros.index', 'cobros.imprimir'] as $ruta) {
        $base = ['idEdicion' => $this->contexto['edicion']->id, 'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $sinCargos->id];
        $this->getJson(route($ruta, $base))->assertOk()->assertViewHas('cargos', fn ($cargos): bool => count($cargos) === 0);
        foreach ([['partido' => $partidoAjeno->id], ['partido' => 999999], ['tipo' => 'inventado'], ['tipo' => null]] as $invalido) {
            $this->getJson(route($ruta, array_replace($base, $invalido)))->assertUnprocessable();
        }
    }
    $this->assertDatabaseCount('cargo_planillas', 1);
});

it('valida entradas y recursos de otra edición sin errores internos', function (): void {
    $otro = crearContextoCargos('Otra edición');
    $equipo = crearEquipoCargos($otro, 'Ajeno');
    $this->actingAs($this->admin);
    foreach ([['estado' => 'inventado'], ['periodo' => ['incorrecto']], ['fecha' => 'mal'], ['idEdicion' => 'mal'],
        ['idCategoria' => $otro['categoria']->id], ['idEquipo' => $equipo->id], ['idFecha' => $otro['fecha']->id],
        ['partido' => 999, 'tipo' => $this->cargo->tipo], ['recibido_desde' => '2026-06-12', 'recibido_hasta' => '2026-06-01']] as $invalido) {
        foreach (['cobros.index', 'cobros.imprimir'] as $ruta) {
            $this->getJson(route($ruta, array_merge(['idEdicion' => $this->contexto['edicion']->id], $invalido)))->assertUnprocessable();
        }
    }
});

it('rechaza corrupción en lugar de ocultar deuda negativa', function (): void {
    $pago = pagoConsulta($this->cargo, $this->admin, 25000);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 10000]);
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 25000]);
    expect(fn () => app(ConsultaCobrosService::class)->consultar(['idEdicion' => $this->contexto['edicion']->id]))->toThrow(RuntimeException::class);
});

it('distingue pagado y exento sin convertir exenciones en ingresos', function (): void {
    $pago = pagoConsulta($this->cargo, $this->admin, 5000, 'transferencia');
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $this->cargo->id, 'importe_ars' => 5000]);
    ExencionCobroPlanilla::create(['cargo_id' => $this->cargo->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 20000]);
    $filtros = ['idEdicion' => $this->contexto['edicion']->id, 'estado' => 'exento'];
    $consulta = app(ConsultaCobrosService::class)->consultar($filtros);
    expect($consulta['cargos'])->toHaveCount(1)
        ->and($consulta['resumen'])->toMatchArray(['ingresos_ars' => 5000, 'credito_ars' => 0, 'deuda_ars' => 0, 'eximido_ars' => 20000])
        ->and($consulta['resumen']['ingresos_por_medio'])->toBe(['transferencia' => 5000]);
    $this->actingAs($this->admin)->get(route('cobros.index', $filtros + ['recibido_hasta' => '2026-06-15']))
        ->assertOk()->assertSee('Exento');
    expect(app(ConsultaCobrosService::class)->consultar(array_merge($filtros, ['estado' => 'pagado']))['cargos'])->toHaveCount(0);
});

it('aísla ingresos por edición y detecta crédito negativo aun con deudas válidas', function (): void {
    $ajeno = crearContextoCargos('Ingresos ajenos');
    $equipo = crearEquipoCargos($ajeno, 'Otro equipo');
    $rival = crearEquipoCargos($ajeno, 'Otro rival');
    $partido = Partido::create(crearDatosPartidoCargos($ajeno, $equipo, $rival));
    $cargoAjeno = CargoPlanilla::create(['tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class,
        'parent_id' => $partido->id, 'idEquipo' => $equipo->id, 'idEdicion' => $ajeno['edicion']->id,
        'idCategoria' => $ajeno['categoria']->id, 'precio_original' => 25000, 'snapshot' => []]);
    pagoConsulta($cargoAjeno, $this->admin, 99000);
    $filtros = ['idEdicion' => $this->contexto['edicion']->id];
    expect(app(ConsultaCobrosService::class)->consultar($filtros)['resumen']['ingresos_ars'])->toBe(0);
    $pago = pagoConsulta($this->cargo, $this->admin, 15000);
    $segundoPartido = $this->cargo->parent->replicate(['public_token']);
    $segundoPartido->save();
    $segundo = CargoPlanilla::create(array_merge($this->cargo->only(['tipo', 'parent_type', 'idEquipo', 'idEdicion', 'idCategoria']),
        ['parent_id' => $segundoPartido->id, 'precio_original' => 25000, 'snapshot' => []]));
    foreach ([$this->cargo, $segundo] as $cargo) {
        AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $cargo->id, 'importe_ars' => 10000]);
    }
    expect(fn () => app(ConsultaCobrosService::class)->consultar($filtros))->toThrow(RuntimeException::class);
});
