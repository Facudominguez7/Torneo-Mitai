<?php

// Planilla única de árbitros (dashboard): reúne partidos regulares e
// instancias finales pendientes, y el dashboard muestra también las finales.

use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Partido;
use App\Models\User;

/**
 * @return array{edicion: Edicion, categoria: Categoria, fecha: Fecha, grupo: Grupo, copa: Copa, fase: Fase}
 */
function contextoPlanillaUnica(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Planilla Unica']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14 Unica']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Unica 7']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'A']);
    $copa = Copa::create(['idEdicion' => $edicion->id, 'nombre' => 'Copa Plata Unica']);
    $fase = Fase::create(['nombre' => 'Semifinal Unica']);

    return compact('edicion', 'categoria', 'fecha', 'grupo', 'copa', 'fase');
}

function equipoPlanillaUnica(array $c, string $nombre): Equipo
{
    return Equipo::create(['idCategoria' => $c['categoria']->id, 'idEdicion' => $c['edicion']->id, 'nombre' => $nombre, 'foto' => 'escudo.png']);
}

function partidoPlanillaUnica(array $c, string $local, string $visitante, string $horario, ?int $goles = null): Partido
{
    return Partido::create([
        'idCategoria' => $c['categoria']->id,
        'idFechas' => $c['fecha']->id,
        'idEquipoLocal' => equipoPlanillaUnica($c, $local)->id,
        'idEquipoVisitante' => equipoPlanillaUnica($c, $visitante)->id,
        'idGrupo' => $c['grupo']->id,
        'idEdicion' => $c['edicion']->id,
        'golesEquipoLocal' => $goles,
        'golesEquipoVisitante' => $goles,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => 0,
    ]);
}

function finalPlanillaUnica(array $c, string $local, string $visitante, string $horario, ?int $goles = null): InstanciaFinal
{
    return InstanciaFinal::create([
        'idCategoria' => $c['categoria']->id,
        'idCopa' => $c['copa']->id,
        'idEdicion' => $c['edicion']->id,
        'idFase' => $c['fase']->id,
        'idEquipoLocal' => equipoPlanillaUnica($c, $local)->id,
        'idEquipoVisitante' => equipoPlanillaUnica($c, $visitante)->id,
        'golesEquipoLocal' => $goles,
        'golesEquipoVisitante' => $goles,
        'horario' => $horario,
        'cancha' => '2',
    ]);
}

function adminPlanillaUnica(): User
{
    return User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
}

it('imprime en una sola planilla los partidos regulares y las finales pendientes', function () {
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Regular Local', 'Regular Visita', '2026-11-15 10:00:00');
    partidoPlanillaUnica($c, 'Jugado Local', 'Jugado Visita', '2026-11-15 10:00:00', 2);
    finalPlanillaUnica($c, 'Final Local', 'Final Visita', '2026-11-15 10:00:00');
    finalPlanillaUnica($c, 'Cerrada Local', 'Cerrada Visita', '2026-11-15 11:00:00', 1);

    $response = $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]));

    $response->assertOk()
        ->assertSee('Regular Local')
        ->assertSee('Fecha Unica 7')
        ->assertSee('Final Local')
        ->assertSee('Copa Plata Unica')
        ->assertSee('Semifinal Unica')
        ->assertDontSee('Jugado Local')
        ->assertDontSee('Cerrada Local');

    $html = $response->getContent();
    expect(substr_count($html, 'data-planilla-tipo="partido"'))->toBe(1)
        ->and(substr_count($html, 'data-planilla-tipo="final"'))->toBe(1)
        // Penales solo en la tarjeta de la final.
        ->and(substr_count($html, '<div class="penales-label">Penales</div>'))->toBe(1);
});

it('filtra la planilla unica por horario en ambos tipos de partido', function () {
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Diez Regular', 'Diez Rival', '2026-11-15 10:00:00');
    partidoPlanillaUnica($c, 'Once Regular', 'Once Rival', '2026-11-15 11:00:00');
    finalPlanillaUnica($c, 'Diez Final', 'Diez Final Rival', '2026-11-15 10:00:00');
    finalPlanillaUnica($c, 'Once Final', 'Once Final Rival', '2026-11-15 11:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id, 'horario' => '10:00']))
        ->assertOk()
        ->assertSee('Diez Regular')
        ->assertSee('Diez Final')
        ->assertDontSee('Once Regular')
        ->assertDontSee('Once Final');
});

it('solo imprime las finales del ultimo dia con finales, igual que el dashboard', function () {
    $c = contextoPlanillaUnica();
    finalPlanillaUnica($c, 'Hoy Final', 'Hoy Rival', '2026-11-15 10:00:00');
    finalPlanillaUnica($c, 'Atrasada Final', 'Atrasada Rival', '2026-11-08 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id, 'horario' => '10:00']))
        ->assertOk()
        ->assertSee('Hoy Final')
        ->assertDontSee('Atrasada Final');
});

it('ordena por horario, cancha y categoria mezclando partidos y finales', function () {
    $c = contextoPlanillaUnica();
    $otraCategoria = Categoria::create(['idEdicion' => $c['edicion']->id, 'nombreCategoria' => 'Sub 16 Unica']);
    finalPlanillaUnica($c, 'Orden Final Once', 'Rival A', '2026-11-15 11:00:00');
    partidoPlanillaUnica($c, 'Orden Regular Diez C1', 'Rival B', '2026-11-15 10:00:00');
    finalPlanillaUnica($c, 'Orden Final Diez C2', 'Rival C', '2026-11-15 10:00:00');
    $mayor = partidoPlanillaUnica($c, 'Orden Mayor Diez C1', 'Rival D', '2026-11-15 10:00:00');
    $mayor->update(['idCategoria' => $otraCategoria->id]);

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSeeInOrder(['Horario 10:00 hs', 'Orden Mayor Diez C1', 'Orden Regular Diez C1', 'Orden Final Diez C2', 'Horario 11:00 hs', 'Orden Final Once']);
});

it('agrupa sin horario en Por definir y no muestra el dia si es una sola jornada', function () {
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Con Hora', 'Rival E', '2026-11-15 10:00:00');
    partidoPlanillaUnica($c, 'Sin Hora', 'Rival F', '2026-11-15 10:00:00')->forceFill(['horario_datetime' => null])->save();

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSeeInOrder(['Horario 10:00 hs', 'Con Hora', 'Horario Por definir', 'Sin Hora'])
        ->assertDontSee('Horario 15/11');
});

it('no imprime partidos viejos sin resultado cuando hay una jornada pendiente mas reciente', function () {
    $this->travelTo('2026-11-22 12:00:00');
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Vieja Regular', 'Rival G', '2026-11-01 18:00:00');
    finalPlanillaUnica($c, 'Reciente Final', 'Rival H', '2026-11-15 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('Reciente Final')
        ->assertSee('Horario 10:00 hs')
        ->assertDontSee('Vieja Regular');
});

it('no imprime finales viejas sin resultado cuando la jornada pendiente es de fase regular', function () {
    $this->travelTo('2026-11-22 12:00:00');
    $c = contextoPlanillaUnica();
    finalPlanillaUnica($c, 'Vieja Final', 'Rival G', '2026-11-08 10:00:00');
    partidoPlanillaUnica($c, 'Reciente Regular', 'Rival H', '2026-11-15 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('Reciente Regular')
        ->assertDontSee('Vieja Final');
});

it('prioriza la proxima jornada y no la tapa un partido reprogramado mas adelante', function () {
    $this->travelTo('2026-08-09 09:00:00');
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Atrasado Regular', 'Rival G', '2026-08-02 10:00:00');
    partidoPlanillaUnica($c, 'Jornada Regular', 'Rival H', '2026-08-09 10:00:00');
    partidoPlanillaUnica($c, 'Reprogramado Regular', 'Rival I', '2026-08-16 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('Jornada Regular')
        ->assertDontSee('Atrasado Regular')
        ->assertDontSee('Reprogramado Regular');
});

it('el filtro de horario no trae partidos de una jornada vieja', function () {
    $this->travelTo('2026-11-22 12:00:00');
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Vieja Dieciocho', 'Rival G', '2026-11-01 18:00:00');
    finalPlanillaUnica($c, 'Reciente Diez', 'Rival H', '2026-11-15 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id, 'horario' => '18:00']))
        ->assertOk()
        ->assertSee('No hay partidos pendientes para el horario 18:00 hs')
        ->assertDontSee('Vieja Dieciocho');
});

it('no imprime partidos sin horario de una fecha ajena a la jornada', function () {
    $this->travelTo('2026-11-22 12:00:00');
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Sin Hora Vieja', 'Rival G', '2026-11-01 18:00:00')->forceFill(['horario_datetime' => null])->save();
    finalPlanillaUnica($c, 'Reciente Final', 'Rival H', '2026-11-15 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('Reciente Final')
        ->assertDontSee('Sin Hora Vieja');
});

it('imprime los partidos sin horario de la ultima fecha cuando ninguno tiene dia', function () {
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Sin Dia', 'Rival G', '2026-11-01 18:00:00')->forceFill(['horario_datetime' => null])->save();

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('Horario Por definir')
        ->assertSee('Sin Dia');
});

it('avisa cuando no hay partidos pendientes', function () {
    $c = contextoPlanillaUnica();

    $this->actingAs(adminPlanillaUnica())
        ->get(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertSee('No hay partidos pendientes.');
});

it('el dashboard ofrece un unico boton de planilla de arbitros', function () {
    $c = contextoPlanillaUnica();

    $html = $this->actingAs(adminPlanillaUnica())
        ->get(route('admin', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-imprimir-planilla-arbitros'))->toBe(1)
        ->and($html)->toContain(route('admin.imprimir-planilla-arbitros', ['idEdicion' => $c['edicion']->id]))
        ->and($html)->toContain('Imprimir Planilla de Árbitros');
});

it('partidos e instancias finales ya no tienen su propia planilla de arbitros', function () {
    $c = contextoPlanillaUnica();
    $admin = adminPlanillaUnica();

    $this->actingAs($admin)->get(route('partido.index', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Imprimir planilla de árbitros');

    $this->actingAs($admin)->get(route('instancia_final.index', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Planilla Árbitros')
        ->assertSee('Imprimir fixture');
});

it('el dashboard muestra las instancias finales del ultimo dia junto a los partidos', function () {
    $c = contextoPlanillaUnica();
    partidoPlanillaUnica($c, 'Dash Regular', 'Dash Rival', '2026-11-15 10:00:00');
    $final = finalPlanillaUnica($c, 'Dash Final', 'Dash Final Rival', '2026-11-15 10:00:00');
    $vieja = finalPlanillaUnica($c, 'Vieja Final', 'Vieja Rival', '2026-11-08 10:00:00', 1);

    $html = $this->actingAs(adminPlanillaUnica())
        ->get(route('admin', ['idEdicion' => $c['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-dashboard-final="'.$final->id.'"')
        ->and($html)->not->toContain('data-dashboard-final="'.$vieja->id.'"')
        ->and($html)->toContain('Dash Regular')
        ->and($html)->toContain('Dash Final')
        ->and($html)->toContain('Copa Plata Unica · Semifinal Unica')
        ->and($html)->toContain('1 de instancias finales')
        ->and($html)->toContain('/instanciaFinal/');

    // Mismo bloque de horario que el partido regular.
    $bloque10 = substr($html, strpos($html, 'data-dashboard-horario="10:00"'));
    expect(strpos($bloque10, 'Dash Regular'))->toBeLessThan(strpos($bloque10, 'Dash Final'));
});

it('el filtro de horario del dashboard incluye y filtra las instancias finales', function () {
    $c = contextoPlanillaUnica();
    finalPlanillaUnica($c, 'Temprana Final', 'Temprana Rival', '2026-11-15 09:00:00');
    finalPlanillaUnica($c, 'Tarde Final', 'Tarde Rival', '2026-11-15 16:00:00');

    $html = $this->actingAs(adminPlanillaUnica())
        ->get(route('admin', ['idEdicion' => $c['edicion']->id, 'horario' => '16:00']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<option value="09:00"')
        ->and($html)->toContain('<option value="16:00"')
        ->and($html)->toContain('Tarde Final')
        ->and($html)->not->toContain('Temprana Final');
});

it('la planilla de una final abierta desde el dashboard vuelve al dashboard', function () {
    $c = contextoPlanillaUnica();
    $final = finalPlanillaUnica($c, 'Vuelta Final', 'Vuelta Rival', '2026-11-15 10:00:00');

    $this->actingAs(adminPlanillaUnica())
        ->get(route('planilla.show', ['partidoId' => $final->id, 'idEdicion' => $c['edicion']->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0, 'origen' => 'admin']))
        ->assertOk()
        ->assertSee(route('admin', ['idEdicion' => $c['edicion']->id]), false);
});
