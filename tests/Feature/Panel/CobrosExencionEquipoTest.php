<?php

use App\Models\AplicacionPagoPlanilla;
use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\CuentaCobroPlanilla;
use App\Models\ExencionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\PagoPlanilla;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Services\Cobros\ConfiguracionCobroService;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->withSession(['_token' => 'cobros-exencion'])->withHeaders(['X-CSRF-TOKEN' => 'cobros-exencion']);
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo exento');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival exento');
    $this->admin = crearAdminCargos();
    app(ConfiguracionCobroService::class)->actualizarTarifa($this->admin, 25000);
    app(ConfiguracionCobroService::class)->activar($this->admin);
    $this->partidos = [];
    $this->cargos = [];
    foreach ([5, 6] as $dia) {
        $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
            'horario_datetime' => "2026-04-0{$dia} 10:00:00",
        ]));
        $this->partidos[] = $partido;
        foreach ([$this->equipo, $this->rival] as $participante) {
            $this->cargos[$dia][$participante->id] = CargoPlanilla::create([
                'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
                'idEquipo' => $participante->id, 'idEdicion' => $this->contexto['edicion']->id,
                'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
                'snapshot' => ['equipo' => ['nombre' => $participante->nombre]],
            ]);
        }
    }
});

function pagarExencionEquipo(CargoPlanilla $cargo, int $importe, int $usuarioId): PagoPlanilla
{
    $cuenta = CuentaCobroPlanilla::query()->firstOrCreate(['idEquipo' => $cargo->idEquipo, 'idEdicion' => $cargo->idEdicion]);
    $pago = PagoPlanilla::create([
        'cuenta_id' => $cuenta->id, 'cargo_origen_id' => $cargo->id, 'usuario_id' => $usuarioId,
        'importe_ars' => $importe, 'medio' => 'efectivo', 'clave_idempotencia' => (string) Str::uuid(),
    ]);
    AplicacionPagoPlanilla::create(['pago_id' => $pago->id, 'cargo_id' => $cargo->id, 'importe_ars' => $importe]);

    return $pago;
}

function rutaPlanillaExencion(Partido $partido): string
{
    return route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $partido->idEdicion, 'tipoPartido' => 'partido', 'horario' => 0]);
}

function rutaEquiposExencion(array $contexto): string
{
    return route('equipo.index', ['idEdicion' => $contexto['edicion']->id]);
}

it('ofrece eximir desde la planilla solo a los equipos que deben', function (): void {
    pagarExencionEquipo($this->cargos[5][$this->rival->id], 25000, $this->admin->id);

    $html = $this->actingAs($this->admin)->get(rutaPlanillaExencion($this->partidos[0]))->assertOk()->getContent();
    $filas = array_slice(explode('data-planilla-cobro="', Str::between($html, 'data-cobros-grilla', 'Cargar resultado')), 1);
    $botones = collect($filas)->mapWithKeys(fn (string $fila): array => [(int) $fila => substr_count($fila, 'data-cobro="exencion"')]);

    expect($botones->all())->toBe([
        $this->cargos[5][$this->equipo->id]->id => 1,
        $this->cargos[5][$this->rival->id]->id => 0,
    ])->and($html)->toContain('Marcar exento');
});

it('refresca la planilla como exenta despues de eximir el cargo', function (): void {
    $cargo = $this->cargos[5][$this->equipo->id];

    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'cargo_id' => $cargo->id, 'motivo' => 'Becado',
    ])->assertSuccessful();

    $this->get(route('cobros.partido', ['idEdicion' => $this->contexto['edicion']->id, 'tipo' => CargoPlanilla::TIPO_PARTIDO, 'partido' => $this->partidos[0]->id]))
        ->assertOk()
        ->assertSee('data-planilla-cobro="'.$cargo->id.'" data-deuda="0" data-estado="exento"', false)
        ->assertSee('Exenta');
    expect(ExencionCobroPlanilla::query()->where('cargo_id', $cargo->id)->value('motivo'))->toBe('Becado');
});

it('ofrece en equipos marcar exento de planilla para la edicion', function (): void {
    $html = $this->actingAs($this->admin)->get(rutaEquiposExencion($this->contexto))->assertOk()->getContent();

    expect($html)->toContain('id="cobros-modal"')
        ->and(substr_count($html, 'data-cobro="exencion_edicion"'))->toBe(2)
        ->and($html)->toContain('Marcar exento de planilla')
        ->and($html)->toContain('&quot;idEquipo&quot;:'.$this->equipo->id);
});

it('muestra exento en equipos cuando la politica existe y no queda deuda', function (): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertSuccessful();

    $html = $this->get(rutaEquiposExencion($this->contexto))->assertOk()->getContent();

    expect(substr_count($html, 'data-cobro="exencion_edicion"'))->toBe(1)
        ->and($html)->toContain('Exento de planilla')
        ->and($html)->not->toContain('&quot;idEquipo&quot;:'.$this->equipo->id);
});

it('exime la edicion completa registrando la politica y el saldo pendiente sin tocar pagos', function (): void {
    $pago = pagarExencionEquipo($this->cargos[6][$this->equipo->id], 10000, $this->admin->id);

    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id, 'motivo' => 'Equipo invitado',
    ])->assertSuccessful()
        ->assertJsonPath('exencion_edicion.cargos_eximidos', 2)
        ->assertJsonPath('exencion_edicion.eximido_ars', 40000)
        ->assertJsonPath('exencion_edicion.politica_nueva', true);

    $politica = PoliticaExencionCobroPlanilla::sole();
    expect($politica->only(['idEquipo', 'idEdicion', 'motivo']))->toBe([
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'motivo' => 'Equipo invitado',
    ])
        ->and(ExencionCobroPlanilla::query()->orderBy('cargo_id')->pluck('importe_ars', 'cargo_id')->all())->toBe([
            $this->cargos[5][$this->equipo->id]->id => 25000,
            $this->cargos[6][$this->equipo->id]->id => 15000,
        ])
        ->and(ExencionCobroPlanilla::query()->whereNotNull('politica_id')->count())->toBe(0)
        ->and($pago->fresh()->importe_ars)->toBe(10000)
        ->and(ExencionCobroPlanilla::query()->whereIn('cargo_id', [$this->cargos[5][$this->rival->id]->id, $this->cargos[6][$this->rival->id]->id])->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->whereIn('accion', ['registrar_politica_exencion', 'eximir_cargo'])->pluck('accion')->countBy()->all())->toBe(['registrar_politica_exencion' => 1, 'eximir_cargo' => 2]);
    $this->assertDatabaseCount('pago_planillas', 1);
    $this->assertDatabaseCount('aplicacion_pago_planillas', 1);
});

it('exime la deuda vieja sin duplicar una politica existente', function (): void {
    PoliticaExencionCobroPlanilla::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id, 'usuario_id' => $this->admin->id]);

    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertSuccessful()
        ->assertJsonPath('exencion_edicion.cargos_eximidos', 2)
        ->assertJsonPath('exencion_edicion.politica_nueva', false);

    expect(PoliticaExencionCobroPlanilla::query()->count())->toBe(1)
        ->and(ExencionCobroPlanilla::query()->count())->toBe(2);
});

it('rechaza eximir la edicion cuando ya esta exento y no debe nada', function (): void {
    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertSuccessful();
    $historial = HistorialCobroPlanilla::query()->count();

    $this->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('politica');

    expect(HistorialCobroPlanilla::query()->count())->toBe($historial)
        ->and(ExencionCobroPlanilla::query()->count())->toBe(2);
});

it('no conserva la politica si falla la exencion de un cargo', function (): void {
    $historial = HistorialCobroPlanilla::query()->count();
    $llamadas = 0;
    ExencionCobroPlanilla::creating(function () use (&$llamadas): void {
        if (++$llamadas === 2) {
            throw new RuntimeException('Falla simulada de la segunda exención.');
        }
    });

    $this->actingAs($this->admin)->postJson('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertServerError();

    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
    expect(HistorialCobroPlanilla::query()->count())->toBe($historial);
});

it('solo un administrador puede eximir la edicion', function (): void {
    $this->actingAs(crearUsuarioConRolCargos('regular'))->post('/Panel/cobros/exenciones-edicion', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ])->assertRedirect('/');

    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
});

it('no muestra la exencion en equipos mientras los cobros estan inactivos', function (): void {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

    $this->actingAs($this->admin)->get(rutaEquiposExencion($this->contexto))->assertOk()
        ->assertSee($this->equipo->nombre)
        ->assertDontSee('data-cobro="exencion_edicion"', false)
        ->assertDontSee('id="cobros-modal"', false);
});
