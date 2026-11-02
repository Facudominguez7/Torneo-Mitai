<?php

use App\Models\CargoPlanilla;
use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo deudor mayor');
    $this->rival = crearEquipoCargos($this->contexto, 'Equipo deudor menor');
    $this->admin = crearAdminCargos();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function cargoInformeDeudores(array $contexto, Equipo $equipo, Equipo $rival, int $precio, string $horario): CargoPlanilla
{
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio,
        'snapshot' => ['equipo' => ['nombre' => $equipo->nombre], 'oponente' => ['nombre' => $rival->nombre]],
    ]);
}

it('agrupa a todos los equipos que deben en un mismo informe, de mayor a menor deuda y con el total', function (): void {
    cargoInformeDeudores($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');
    cargoInformeDeudores($this->contexto, $this->equipo, $this->rival, 15000, '2026-05-30 09:45:00');
    cargoInformeDeudores($this->contexto, $this->rival, $this->equipo, 5000, '2026-06-06 10:30:00');
    cargoInformeDeudores($this->contexto, $this->rival, $this->equipo, 9000, '2026-06-20 10:30:00');

    $html = $this->actingAs($this->admin)
        ->get(route('cobros.informe-deudores', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();

    expect($html)->toContain('Deudores de planillas')
        ->and($html)->toContain('Total adeudado')
        ->and($html)->toContain('$ 40.000')
        ->and($html)->toContain('$ 35.000')
        ->and($html)->toContain('$ 5.000')
        ->and($html)->not->toContain('$ 9.000')
        ->and(stripos($html, 'Equipo deudor mayor'))->toBeInt()->toBeLessThan(stripos($html, 'Equipo deudor menor'))
        ->and(substr_count($html, 'data-informe-deudor='))->toBe(2);
});

it('avisa que nadie debe cuando la edicion no tiene deudas a hoy', function (): void {
    $this->actingAs($this->admin)
        ->get(route('cobros.informe-deudores', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->assertSee('Ningún equipo debe planillas a hoy.');
});

it('ofrece el informe de deudores desde la pantalla de cobros en otra pestana', function (): void {
    cargoInformeDeudores($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    $html = $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()->getContent();
    preg_match('/<a[^>]*data-cobros-informe-deudores[^>]*>/', $html, $enlace);

    expect($enlace)->not->toBeEmpty()
        ->and($enlace[0])->toContain('target="_blank"')
        ->and($enlace[0])->toContain('aria-label="Informe de todos los deudores (se abre en otra pestaña)"')
        ->and(html_entity_decode($enlace[0]))->toContain(route('cobros.informe-deudores', ['idEdicion' => $this->contexto['edicion']->id]));
});

it('ya no muestra los filtros de cargos ni la tabla de cargos ni los totales en la pantalla de cobros', function (): void {
    cargoInformeDeudores($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    $this->actingAs($this->admin)->get(route('cobros.index', ['idEdicion' => $this->contexto['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Filtros de cargos')
        ->assertDontSee('Aplicar filtros')
        ->assertDontSee('Resumen de cobros')
        ->assertDontSee('Deuda de los cargos filtrados')
        ->assertSee('Equipos que deben planillas');
});

it('protege el informe de deudores para administradores', function (): void {
    $ruta = route('cobros.informe-deudores', ['idEdicion' => $this->contexto['edicion']->id]);

    $this->get($ruta)->assertNotFound();
    $this->actingAs(crearUsuarioConRolCargos('regular'))->get($ruta)->assertRedirect('/');
});
