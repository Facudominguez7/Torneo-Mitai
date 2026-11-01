<?php

// Carga manual de deudas de planilla desde /Panel/cobros: el admin busca el
// equipo, carga importe, fecha (por defecto hoy) y motivo opcional, sin partido.

use App\Models\CargoPlanilla;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\HistorialCobroPlanilla;
use App\Services\Cobros\ConfiguracionCobroService;
use App\Services\Cobros\DeudasManualesCobroService;

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
    $this->entrada = [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
        'importe_ars' => '25000', 'fecha' => now()->subDays(5)->toDateString(), 'motivo' => 'Planilla anterior',
        'clave_idempotencia' => '7d2c1a52-3f0b-4c8e-9a61-2b7e5d4c9f10',
    ];
});

it('muestra el formulario del equipo con importe, fecha de hoy y motivo, sin partidos', function () {
    $respuesta = $this->actingAs($this->admin)->get(route('cobros.deuda-manual', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ]));

    $respuesta->assertOk()
        ->assertSee('Cargar deuda de planilla')
        ->assertSee('value="25000"', false)
        ->assertSee('type="date" name="fecha" value="'.now()->toDateString().'" max="'.now()->toDateString().'"', false)
        ->assertSee('name="motivo"', false)
        ->assertDontSee('name="partido"', false)
        ->assertDontSee('Partido adeudado');
});

it('busca el equipo por nombre y muestra la categoría de esta edición en cada resultado', function () {
    $otraCategoria = Categoria::create(['idEdicion' => $this->contexto['edicion']->id, 'nombreCategoria' => 'Sub 10 Cobros']);
    $homonimo = crearEquipoCargos(['categoria' => $otraCategoria] + $this->contexto, 'Equipo Deudor HTTP');
    $edicionAnterior = crearContextoCargos('Edicion Anterior HTTP');
    $edicionAnterior['categoria']->update(['nombreCategoria' => 'Categoria Vieja']);
    Equipo::query()->whereKey($this->equipo->id)->update(['idCategoria' => $edicionAnterior['categoria']->id]);

    $html = $this->actingAs($this->admin)->get(route('cobros.deuda-manual', [
        'idEdicion' => $this->contexto['edicion']->id, 'buscar' => 'deudor',
    ]))->assertOk()->assertSee('Buscar equipo')->assertSee('name="buscar"', false)
        ->assertDontSee('<select id="idEquipo"', false)->assertDontSee('Categoria Vieja')->getContent();

    $resultados = [];
    preg_match_all('/<li data-equipo-opcion="(\d+)"[^>]*?(style="display: none")?>(.*?)<\/li>/s', $html, $resultados, PREG_SET_ORDER);
    $porEquipo = collect($resultados)->mapWithKeys(fn (array $r): array => [(int) $r[1] => ['oculto' => $r[2] !== '', 'texto' => preg_replace('/\s+/', ' ', strip_tags($r[3]))]]);

    expect($porEquipo[$this->equipo->id])->toBe(['oculto' => false, 'texto' => ' Equipo Deudor Http Sub 12 Cobros '])
        ->and($porEquipo[$homonimo->id])->toBe(['oculto' => false, 'texto' => ' Equipo Deudor Http Sub 10 Cobros '])
        ->and($porEquipo[$this->rival->id]['oculto'])->toBeTrue()
        ->and($html)->toContain('href="'.e(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $homonimo->id])).'"');
});

it('avisa cuando ningún equipo coincide con la búsqueda', function () {
    $this->actingAs($this->admin)->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'buscar' => 'inexistente']))
        ->assertOk()->assertSee('No hay equipos con ese nombre en esta edición.');
});

it('muestra el equipo elegido con su categoría y permite cambiarlo', function () {
    $this->actingAs($this->admin)->get(route('cobros.deuda-manual', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ]))->assertOk()
        ->assertSee('Equipo Deudor Http · Sub 12 Cobros')
        ->assertSee('Cambiar equipo')
        ->assertSee('href="'.e(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id])).'"', false);
});

it('sin equipo elegido pide elegirlo y no muestra el formulario de la deuda', function () {
    $this->actingAs($this->admin)->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Buscá el equipo para cargarle una deuda.')
        ->assertDontSee('name="importe_ars"', false);
});

it('carga la deuda sin partido y vuelve al formulario con el aviso', function () {
    $respuesta = $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada);

    $respuesta->assertRedirect(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Deuda cargada: Equipo Deudor Http debe $ 25.000.');
    $cargo = CargoPlanilla::query()->sole();
    expect($cargo->tipo)->toBe(CargoPlanilla::TIPO_DEUDA_MANUAL)
        ->and($cargo->parent_id)->toBeNull()
        ->and((int) $cargo->idEquipo)->toBe($this->equipo->id)
        ->and($cargo->precio_original)->toBe(25000)
        ->and($cargo->snapshot['fecha_deuda'])->toBe($this->entrada['fecha'])
        ->and($cargo->snapshot['motivo'])->toBe('Planilla anterior');
    expect(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(1);
});

it('un doble envío con la misma clave carga una sola deuda', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Deuda cargada: Equipo Deudor Http debe $ 25.000.');

    expect(CargoPlanilla::query()->count())->toBe(1)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(1);
});

it('rechaza la misma clave con otros datos sin crear otra deuda', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['importe_ars' => '9000'] + $this->entrada)
        ->assertSessionHasErrors(['clave_idempotencia' => 'Esta carga ya se usó con otros datos. Recargá la página y volvé a cargar la deuda.']);

    expect(CargoPlanilla::query()->sole()->precio_original)->toBe(25000)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(1);
});

it('después de rechazar una clave usada, el formulario trae una clave nueva', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['importe_ars' => '9000'] + $this->entrada)
        ->assertSessionHasErrors('clave_idempotencia');

    $html = $this->actingAs($this->admin)
        ->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->getContent();

    expect($html)->toContain('name="clave_idempotencia"')
        ->and($html)->not->toContain($this->entrada['clave_idempotencia'])
        ->and($html)->toContain('value="9000"');
});

it('dos claves distintas cargan dos deudas', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $this->entrada)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['clave_idempotencia' => '0b9f4e2d-6a71-4c3b-8d15-9e2f7a6c1b40'] + $this->entrada)
        ->assertSessionHasNoErrors();

    expect(CargoPlanilla::query()->count())->toBe(2);
});

it('el formulario lleva una clave de reintento y bloquea el botón al enviar', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->getContent();

    expect($html)->toMatch('/name="clave_idempotencia" value="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"/')
        ->and($html)->toContain('data-deuda-manual-enviar')
        ->and($html)->toContain('Cargando…');
});

it('acepta el importe con separador de miles y el motivo vacío', function () {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['importe_ars' => '$ 30.000', 'motivo' => ''] + $this->entrada)
        ->assertSessionHasNoErrors();

    $cargo = CargoPlanilla::query()->sole();
    expect($cargo->precio_original)->toBe(30000)
        ->and($cargo->snapshot['motivo'])->toBeNull();
});

it('rechaza un equipo que no está en la edición sin crear cargo ni historial', function () {
    $otra = crearContextoCargos('Otra edición manual');
    $ajeno = crearEquipoCargos($otra, 'Equipo Ajeno');

    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), ['idEquipo' => $ajeno->id] + $this->entrada)
        ->assertSessionHasErrors(['idEquipo' => 'El equipo no está inscripto en esta edición.']);

    expect(CargoPlanilla::query()->count())->toBe(0)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'cargar_deuda_manual')->count())->toBe(0);
});

it('valida los campos del formulario', function (array $cambios, string $campo, string $mensaje) {
    $this->actingAs($this->admin)->post(route('cobros.deudas-manuales'), $cambios + $this->entrada)
        ->assertSessionHasErrors([$campo => $mensaje]);

    expect(CargoPlanilla::query()->count())->toBe(0);
})->with([
    'sin equipo' => [['idEquipo' => ''], 'idEquipo', 'Elegí el equipo.'],
    'importe cero' => [['importe_ars' => '0'], 'importe_ars', 'El importe debe ser mayor a 0.'],
    'importe con letras' => [['importe_ars' => 'mucho'], 'importe_ars', 'El importe debe ser un número entero de pesos.'],
    'importe con puntos mal puestos' => [['importe_ars' => '25.5'], 'importe_ars', 'El importe debe ser un número entero de pesos.'],
    'sin fecha' => [['fecha' => ''], 'fecha', 'Ingresá la fecha de la deuda.'],
    'fecha inválida' => [['fecha' => '31/10/2026'], 'fecha', 'La fecha no es válida.'],
    'fecha futura' => [['fecha' => '2999-01-01'], 'fecha', 'La fecha no puede ser posterior a hoy.'],
    'motivo largo' => [['motivo' => str_repeat('a', 256)], 'motivo', 'El motivo puede tener hasta 255 caracteres.'],
    'sin clave de reintento' => [['clave_idempotencia' => ''], 'clave_idempotencia', 'Recargá la página y volvé a cargar la deuda.'],
    'clave de reintento inválida' => [['clave_idempotencia' => 'abc'], 'clave_idempotencia', 'La clave de reintento no es válida.'],
]);

it('solo el administrador accede a la carga manual', function (string $rol) {
    $usuario = crearUsuarioConRolCargos($rol);
    $usuario->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($usuario)->post(route('cobros.deudas-manuales'), $this->entrada)->assertRedirect('/');
    $this->actingAs($usuario)->get(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]))->assertRedirect('/');
    expect(CargoPlanilla::query()->count())->toBe(0);
})->with(['editor']);

it('la consulta muestra la deuda manual sin partido ni "No vino"', function () {
    app(DeudasManualesCobroService::class)->cargarDeuda($this->admin, $this->contexto['edicion']->id, $this->equipo->id, 25000, '2026-10-20', 'Planilla vieja');

    $html = $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->assertSee('Deuda cargada a mano')->assertSee('2026-10-20')->getContent();

    expect($html)->not->toContain('vs. Sin rival histórico')
        ->and($html)->not->toContain('data-cobro="no_vino"')
        ->and($html)->toContain('data-cobro="pago"');

    $this->get(route('cobros.detalle', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id]))
        ->assertOk()->assertSee('2026-10-20 · Deuda cargada a mano')->assertDontSee('2026-10-20 00:00');
});

it('enlaza la carga manual desde la consulta de cobros', function () {
    $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertSee(route('cobros.deuda-manual', ['idEdicion' => $this->contexto['edicion']->id]), false);
});
