<?php

// Carga manual de deudas de planilla desde /Panel/cobros: el admin elige el
// equipo y un partido ya jugado sin cargo; la deuda queda como cualquier cargo.

use App\Models\CargoPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\Partido;
use App\Services\Cobros\ConfiguracionCobroService;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    config(['logging.default' => 'stderr']);
    $this->withSession(['_token' => 'cobros-deuda-manual'])->withHeaders(['X-CSRF-TOKEN' => 'cobros-deuda-manual']);
    $this->contexto = crearContextoCargos('Edicion Deuda Manual HTTP');
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Deudor HTTP');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival HTTP');
    $this->admin = crearAdminCargos();
    app(ConfiguracionCobroService::class)->actualizarTarifa($this->admin, 25000);
    app(ConfiguracionCobroService::class)->activar($this->admin);
    $this->jugado = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => now()->subWeek()->setTime(11, 0)->format('Y-m-d H:i:s'), 'cancha' => 3,
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0, 'jugado' => 1,
    ]));
    $this->conCargo = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => now()->subDays(3)->setTime(9, 30)->format('Y-m-d H:i:s'),
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'jugado' => 1,
    ]));
    CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $this->conCargo->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000,
        'snapshot' => ['equipo' => ['nombre' => $this->equipo->nombre]],
    ]);
    $this->futuro = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $this->rival, [
        'horario_datetime' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 0, 'jugado' => 1,
    ]));
    $this->entrada = [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
        'partido' => CargoPlanilla::TIPO_PARTIDO.'-'.$this->jugado->id, 'importe_ars' => '25000', 'motivo' => 'Planilla anterior',
    ];
});

it('muestra el formulario con los partidos jugados del equipo y la tarifa vigente', function () {
    $respuesta = $this->actingAs($this->admin)->get(route('cobros.deuda-manual', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ]));

    $respuesta->assertOk()
        ->assertSee('Cargar deuda de planilla')
        ->assertSee('Equipo Deudor Http')
        ->assertSee('value="'.CargoPlanilla::TIPO_PARTIDO.'-'.$this->jugado->id.'"', false)
        ->assertSee('vs Rival Http · Cancha 3')
        ->assertSee('Ya tiene cargo')
        ->assertSee('value="25000"', false)
        ->assertDontSee('value="'.CargoPlanilla::TIPO_PARTIDO.'-'.$this->futuro->id.'"', false);
    expect(substr_count($respuesta->getContent(), 'name="partido"'))->toBe(2);
});

it('sin equipo elegido pide elegirlo y no lista partidos', function () {
    $this->actingAs($this->admin)->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Elegí un equipo para ver sus partidos jugados.')
        ->assertDontSee('name="partido"', false);
});

it('carga la deuda y vuelve al formulario con el aviso', function () {
    $respuesta = $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada);

    $respuesta->assertRedirect(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id]))
        ->assertSessionHas('success', 'Deuda cargada: Equipo Deudor Http debe $ 25.000 de la planilla del partido.');
    $cargo = CargoPlanilla::query()->where('parent_id', $this->jugado->id)->sole();
    expect((int) $cargo->idEquipo)->toBe($this->equipo->id)
        ->and($cargo->precio_original)->toBe(25000)
        ->and($cargo->snapshot['origen'])->toBe('manual')
        ->and($cargo->snapshot['motivo'])->toBe('Planilla anterior');
    expect(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(1);
});

it('acepta el importe con separador de miles', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['importe_ars' => '$ 30.000'] + $this->entrada)
        ->assertSessionHasNoErrors();

    expect(CargoPlanilla::query()->where('parent_id', $this->jugado->id)->sole()->precio_original)->toBe(30000);
});

it('rechaza importes con puntos que no separan miles correctamente', function (string $importe) {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['importe_ars' => $importe] + $this->entrada)
        ->assertSessionHasErrors('importe_ars');

    expect(CargoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
})->with(['25.5', '1.2.3']);

it('rechaza un partido de otra edición sin crear cargo ni historial', function () {
    $otra = crearContextoCargos('Otra edición manual');

    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['idEdicion' => $otra['edicion']->id] + $this->entrada)
        ->assertSessionHasErrors('partido');

    expect(CargoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
});

it('rechaza un partido que ya tiene cargo sin crear otro', function () {
    $this->actingAs($this->admin)
        ->from(route('cobros.deuda-manual'))
        ->post(route('cobros.deudas-manuales'), ['partido' => CargoPlanilla::TIPO_PARTIDO.'-'.$this->conCargo->id] + $this->entrada)
        ->assertSessionHasErrors(['partido' => 'El equipo ya tiene un cargo de planilla en este partido.']);

    expect(CargoPlanilla::query()->count())->toBe(1);
});

it('valida los campos del formulario', function (array $cambios, string $campo, string $mensaje) {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $cambios + $this->entrada)
        ->assertSessionHasErrors([$campo => $mensaje]);

    expect(CargoPlanilla::query()->count())->toBe(1);
})->with([
    'sin partido' => [['partido' => ''], 'partido', 'Elegí el partido adeudado.'],
    'tipo inválido' => [['partido' => 'otro-1'], 'tipo', 'Elegí un partido de la lista.'],
    'importe cero' => [['importe_ars' => '0'], 'importe_ars', 'El importe debe ser mayor a 0.'],
    'importe con letras' => [['importe_ars' => 'mucho'], 'importe_ars', 'El importe debe ser un número entero de pesos.'],
    'motivo largo' => [['motivo' => str_repeat('a', 256)], 'motivo', 'El motivo puede tener hasta 255 caracteres.'],
]);

it('solo el administrador accede a la carga manual', function (string $rol) {
    $usuario = crearUsuarioConRolCargos($rol);
    $usuario->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($usuario)->post(route('cobros.deudas-manuales'), $this->entrada)->assertRedirect('/');
    $this->actingAs($usuario)->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]))->assertRedirect('/');
    expect(CargoPlanilla::query()->count())->toBe(1);
})->with(['editor']);

it('enlaza la carga manual desde la consulta de cobros', function () {
    $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSee(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]), false);
});
