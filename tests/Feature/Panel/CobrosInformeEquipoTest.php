<?php

use App\Models\CargoPlanilla;
use App\Models\Equipo;
use App\Models\ExencionCobroPlanilla;
use App\Models\Partido;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo informe');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival informe');
    $this->admin = crearAdminCargos();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function cargoInformeEquipo(array $contexto, Equipo $equipo, Equipo $rival, int $precio, string $horario): CargoPlanilla
{
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario, 'cancha' => 3]));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio,
        'snapshot' => ['equipo' => ['nombre' => $equipo->nombre], 'oponente' => ['nombre' => $rival->nombre]],
    ]);
}

function rutaInformeEquipo(array $contexto, Equipo $equipo): string
{
    return route('cobros.informe-equipo', ['idEdicion' => $contexto['edicion']->id, 'idEquipo' => $equipo->id]);
}

it('separa lo que el equipo debe de lo que tiene al dia, con el partido de cada planilla y sin firmas', function (): void {
    cargoInformeEquipo($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');
    $eximido = cargoInformeEquipo($this->contexto, $this->equipo, $this->rival, 15000, '2026-05-30 09:45:00');
    ExencionCobroPlanilla::create(['cargo_id' => $eximido->id, 'usuario_id' => $this->admin->id, 'importe_ars' => 15000]);
    cargoInformeEquipo($this->contexto, $this->equipo, $this->rival, 99000, '2026-07-01 10:00:00');

    $this->actingAs($this->admin)->get(rutaInformeEquipo($this->contexto, $this->equipo))
        ->assertOk()
        ->assertSeeInOrder([$this->equipo->nombre, 'Saldo a pagar', '$ 20.000',
            'Debe · 1 planilla', 'Fecha 1 vs '.$this->rival->nombre, 'sáb 06/06 · 10:30 · Cancha 3', '$ 20.000', 'Total', '$ 20.000',
            'Al día · 1 planilla', 'Fecha 1 vs '.$this->rival->nombre, 'sáb 30/05', 'Eximida'])
        ->assertDontSee('$ 99.000')
        ->assertDontSee('Delegado del equipo')
        ->assertDontSee('firmar');
});

it('informa que el equipo no debe planillas cuando esta al dia', function (): void {
    $this->actingAs($this->admin)->get(rutaInformeEquipo($this->contexto, $this->equipo))
        ->assertOk()
        ->assertSee('$ 0')
        ->assertSee('No debe planillas de partidos jugados.');
});

it('exige el equipo y solo lo muestra a administradores', function (): void {
    $this->actingAs($this->admin)->get(route('cobros.informe-equipo', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertStatus(422);
    $this->actingAs(crearUsuarioConRolCargos('regular'))->get(rutaInformeEquipo($this->contexto, $this->equipo))
        ->assertRedirect('/');
});

it('rechaza el informe de un equipo que no pertenece a la edicion en lugar de mostrarlo al dia', function (): void {
    $otra = crearContextoCargos('Otra edicion informe');
    $ajeno = crearEquipoCargos($otra, 'Equipo de otra edicion');

    $this->actingAs($this->admin)->getJson(rutaInformeEquipo($this->contexto, $ajeno))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idEquipo' => 'El equipo no pertenece a la edición seleccionada.']);
    $this->actingAs($this->admin)->get(rutaInformeEquipo($this->contexto, $ajeno))
        ->assertRedirect()->assertSessionHasErrors('idEquipo');
});

it('rechaza el informe con un equipo o una edicion inexistentes', function (array $parametros, string $campo): void {
    $ruta = route('cobros.informe-equipo', array_merge([
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->equipo->id,
    ], $parametros));

    $this->actingAs($this->admin)->getJson($ruta)->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'equipo inexistente' => [['idEquipo' => 999999], 'idEquipo'],
    'edicion inexistente' => [['idEdicion' => 999999], 'idEdicion'],
]);

it('ofrece el informe desde la tarjeta del deudor sin volverla un enlace', function (): void {
    cargoInformeEquipo($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    $html = $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();

    expect($html)->toContain('href="'.e(rutaInformeEquipo($this->contexto, $this->equipo)).'"')
        ->and($html)->toContain('data-cobros-informe="'.$this->equipo->id.'"')
        ->and($html)->toContain('Ver informe')
        ->and($html)->not->toContain('Informe para firmar')
        ->and($html)->not->toMatch('/<a[^>]*data-cobros-deudor/');
});
