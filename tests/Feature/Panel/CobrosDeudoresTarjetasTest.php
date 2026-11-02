<?php

use App\Models\CargoPlanilla;
use App\Models\Equipo;
use App\Models\Partido;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-15 12:00:00');
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo tarjeta');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival tarjeta');
    $this->admin = crearAdminCargos();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function cargoTarjetaDeudor(array $contexto, Equipo $equipo, Equipo $rival, int $precio, string $horario): CargoPlanilla
{
    $partido = Partido::create(crearDatosPartidoCargos($contexto, $equipo, $rival, ['horario_datetime' => $horario]));

    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $equipo->id, 'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id, 'precio_original' => $precio,
        'snapshot' => ['equipo' => ['nombre' => $equipo->nombre], 'oponente' => ['nombre' => $rival->nombre]],
    ]);
}

function htmlTarjetasDeudores(object $test): string
{
    return $test->actingAs($test->admin)->get(route('cobros.index', ['idEdicion' => $test->contexto['edicion']->id]))
        ->assertOk()->getContent();
}

it('ofrece registrar el pago de la planilla adeudada mas antigua desde la tarjeta cuando los cobros estan activos', function (): void {
    activarCobrosCargos();
    cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');
    $antigua = cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 15000, '2026-05-30 09:45:00');

    $html = htmlTarjetasDeudores($this);
    preg_match('/<button[^>]*data-cobros-pagar-deudor="'.$this->equipo->id.'"[^>]*>/', $html, $boton);

    expect($boton)->not->toBeEmpty()
        ->and($boton[0])->toContain('data-cobro="pago"')
        ->and(json_decode(html_entity_decode(Str::between($boton[0], 'data-cargo="', '"')), true))
        ->toMatchArray(['id' => $antigua->id, 'deuda_ars' => 15000])
        ->and($html)->toContain('Registrar pago');
});

it('no ofrece registrar pagos desde la tarjeta mientras los cobros estan sin activar', function (): void {
    cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    expect(htmlTarjetasDeudores($this))->not->toContain('data-cobros-pagar-deudor=');
});

it('nombra al equipo en el enlace al informe y avisa que abre otra pestana', function (): void {
    cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    expect(htmlTarjetasDeudores($this))
        ->toContain('aria-label="Ver informe de '.e($this->equipo->nombre).' (se abre en otra pestaña)"');
});

it('muestra tres planillas por tarjeta y agrupa el resto en un desplegable', function (): void {
    foreach (['2026-05-02', '2026-05-09', '2026-05-16', '2026-05-23', '2026-05-30'] as $dia) {
        cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 10000, $dia.' 10:00:00');
    }

    $html = htmlTarjetasDeudores($this);

    expect($html)->toMatch('/<details[^>]*>\s*<summary[^>]*>\s*\+2 planillas más\s*<\/summary>/')
        ->and(substr_count(Str::before($html, '<details'), 'data-cobros-planilla'))->toBe(3)
        ->and(substr_count($html, 'data-cobros-planilla'))->toBe(5);
});

it('permite buscar por equipo y filtrar por categoria e indica el orden por deuda', function (): void {
    cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');
    cargoTarjetaDeudor($this->contexto, $this->rival, $this->equipo, 5000, '2026-06-06 10:30:00');

    $html = htmlTarjetasDeudores($this);

    expect($html)->toContain('data-cobros-deudores-buscar')
        ->and($html)->toContain('<option value="'.e($this->contexto['categoria']->nombreCategoria).'">')
        ->and($html)->toContain('de mayor a menor deuda')
        ->and($html)->toContain('data-categoria="'.e($this->contexto['categoria']->nombreCategoria).'"');
});

it('no usa la franja lateral de color en las tarjetas', function (): void {
    cargoTarjetaDeudor($this->contexto, $this->equipo, $this->rival, 20000, '2026-06-06 10:30:00');

    expect(htmlTarjetasDeudores($this))->not->toMatch('/data-cobros-deudor="[^"]*"[^>]*border-l-4/');
});
