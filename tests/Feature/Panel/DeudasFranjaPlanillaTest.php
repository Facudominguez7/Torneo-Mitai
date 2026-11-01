<?php

use App\Models\CargoPlanilla;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Equipo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

function partidoFranja(array $contexto, Equipo $local, Equipo $visitante, string $fecha): Partido
{
    return Partido::create(crearDatosPartidoCargos($contexto, $local, $visitante, ['horario_datetime' => $fecha]));
}

function cargoFranja(array $contexto, Partido $partido, Equipo $equipo, Equipo $rival, int $importe): CargoPlanilla
{
    return CargoPlanilla::create([
        'tipo' => CargoPlanilla::TIPO_PARTIDO,
        'parent_type' => Partido::class,
        'parent_id' => $partido->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'precio_original' => $importe,
        'snapshot' => ['equipo' => ['nombre' => $equipo->nombre], 'oponente' => ['nombre' => $rival->nombre]],
    ]);
}

function urlPlanillaFranja(Partido $partido): string
{
    return route('planilla.show', ['partidoId' => $partido->id, 'idEdicion' => $partido->idEdicion, 'tipoPartido' => 'partido', 'horario' => 0]);
}

function urlPollingFranja(string $html): string
{
    preg_match('/<[^>]*data-cobros-seccion\b[^>]*>/s', $html, $seccion);
    preg_match('/data-url="([^"]+)"/', $seccion[0] ?? '', $url);

    expect($url[1] ?? null)->not->toBeNull();

    return html_entity_decode($url[1], ENT_QUOTES, 'UTF-8');
}

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->local = crearEquipoCargos($this->contexto, 'Local franja');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante franja');
    $this->rival = crearEquipoCargos($this->contexto, 'Rival histórico');
    $this->partido = partidoFranja($this->contexto, $this->local, $this->visitante, '2026-06-15 10:00:00');
    $this->anterior = partidoFranja($this->contexto, $this->local, $this->rival, '2026-06-14 10:00:00');
    $this->mismoDia = partidoFranja($this->contexto, $this->local, $this->rival, '2026-06-15 20:00:00');
    $this->futuro = partidoFranja($this->contexto, $this->local, $this->rival, '2026-06-16 10:00:00');
    $this->cargoAnterior = cargoFranja($this->contexto, $this->anterior, $this->local, $this->rival, 10000);
    cargoFranja($this->contexto, $this->mismoDia, $this->local, $this->rival, 15000);
    cargoFranja($this->contexto, $this->futuro, $this->local, $this->rival, 99000);
    $this->admin = crearAdminCargos();
    $this->editor = crearUsuarioConRolCargos('editor');
    activarCobrosCargos();
});

it('muestra a admin y editor solo las deudas de los dos equipos hasta el día de la planilla inclusive', function (string $rol): void {
    $usuario = $rol === 'admin' ? $this->admin : $this->editor;
    $otraEdicion = crearContextoCargos('Otra edición franja');
    $otroLocal = crearEquipoCargos($otraEdicion, 'Equipo ajeno');
    $otroRival = crearEquipoCargos($otraEdicion, 'Rival ajeno');
    $ajeno = partidoFranja($otraEdicion, $otroLocal, $otroRival, '2026-06-14 10:00:00');
    cargoFranja($otraEdicion, $ajeno, $otroLocal, $otroRival, 88000);
    cargoFranja($otraEdicion, $ajeno, $this->local, $otroRival, 77000);

    $respuesta = $this->actingAs($usuario)->get(urlPlanillaFranja($this->partido))->assertOk();
    $html = $respuesta->getContent();
    $respuesta->assertSee($this->local->nombre)->assertSee($this->visitante->nombre)
        ->assertSee('25.000')->assertSee('Al día')->assertSee($this->rival->nombre)
        ->assertSee('10.000')->assertSee('15.000')
        ->assertDontSee('99.000')->assertDontSee('88.000')->assertDontSee('77.000')->assertDontSee('Equipo ajeno');
    expect($html)->toContain('<select')->toContain('data-cobros-seccion')
        ->toContain('dom 14/06')->toContain('lun 15/06')->toContain('Domingo 14/06,')
        ->toContain('vs '.$this->rival->nombre)->toContain('en 2 planillas')->toContain('No debe planillas de esta edición.')
        ->toContain($this->rival->nombre)->toContain((string) $this->cargoAnterior->id)
        ->toContain('data-deuda-selector')->toContain('x-model="seleccion"')->toContain('x-show="seleccion ===');
})->with(['admin', 'editor']);

it('permite consultar la franja de una final sin mezclar ediciones', function (): void {
    $final = InstanciaFinal::create(crearDatosFinalCargos($this->contexto, $this->local, $this->visitante, ['horario' => '2026-06-15 10:00:00']));
    $url = route('planilla.show', ['partidoId' => $final->id, 'idEdicion' => $this->contexto['edicion']->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0]);
    $respuesta = $this->actingAs($this->editor)->get($url)->assertOk()->assertSee('25.000')->assertDontSee('99.000');
    $polling = urlPollingFranja($respuesta->getContent());
    $this->get($polling)->assertOk()->assertSee('25.000')->assertDontSee('99.000');
    $otra = crearContextoCargos('Edición incorrecta');
    $this->get(route('planilla.show', ['partidoId' => $final->id, 'idEdicion' => $otra['edicion']->id, 'tipoPartido' => 'instanciaFinal', 'horario' => 0]))->assertNotFound();
});

it('preserva acciones administrativas y no las expone al editor', function (): void {
    cargoFranja($this->contexto, $this->partido, $this->local, $this->visitante, 25000);
    $admin = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk();
    $admin->assertSee('id="cobros-modal"', false)->assertSee('data-cobro="pago"', false)
        ->assertSee('data-cobro="exencion"', false)->assertSee('data-cobro="no_vino"', false);

    $editor = $this->actingAs($this->editor)->get(urlPlanillaFranja($this->partido))->assertOk();
    $editor->assertDontSee('data-cobro="pago"', false)->assertDontSee('data-cobro="exencion"', false)
        ->assertDontSee('data-cobro="no_vino"', false);
    foreach (['/Panel/cobros', '/Panel/cobros/detalle?idEdicion='.$this->contexto['edicion']->id.'&idEquipo='.$this->local->id,
        '/Panel/cobros/partido?idEdicion='.$this->contexto['edicion']->id.'&tipo=partido&partido='.$this->partido->id] as $url) {
        $this->get($url)->assertRedirect('/');
    }
    $this->postJson('/Panel/cobros/pagos', ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->local->id,
        'cargo_id' => $this->cargoAnterior->id, 'importe_efectivo_ars' => 10000, 'clave_idempotencia' => (string) Str::uuid()])->assertRedirect('/');
});

it('conserva operaciones existentes de exención y no vino para el admin', function (): void {
    $actual = cargoFranja($this->contexto, $this->partido, $this->local, $this->visitante, 25000);
    $entrada = ['idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->local->id, 'cargo_id' => $actual->id];
    $this->actingAs($this->admin)->postJson('/Panel/cobros/no-vino', $entrada)->assertSuccessful();
    $this->postJson('/Panel/cobros/no-vino-deshacer', $entrada)->assertSuccessful();
    $this->postJson('/Panel/cobros/exenciones', $entrada)->assertSuccessful();
    $this->get(urlPlanillaFranja($this->partido))->assertOk()->assertSee('25.000');
});

function jugadorFranja(Partido|InstanciaFinal $partido, Equipo $equipo, string $dni, string $nombre): void
{
    Jugador::create(['dni' => $dni, 'nombre' => $nombre, 'apellido' => 'Privado']);
    ListaBuenaFe::create([
        'dni_jugador' => $dni, 'idEquipo' => $equipo->id, 'idEdicion' => $partido->idEdicion,
        'idCategoria' => $partido->idCategoria, 'fecha_nacimiento' => '2012-03-17',
        'fecha_alta' => now(), 'activo' => true,
    ]);
    PlanillaJugador::create([
        'partido_id' => $partido->id, 'partido_type' => $partido::class,
        'dni_jugador' => $dni, 'idEquipo' => $equipo->id, 'idEdicion' => $partido->idEdicion,
        'idCategoria' => $partido->idCategoria, 'numero_camiseta' => 19,
        'fecha_nacimiento' => '2012-03-17', 'goles' => 0, 'asistio' => true,
    ]);
}

it('limita al editor a deudas sin datos personales ni controles de edición', function (): void {
    jugadorFranja($this->partido, $this->local, '87654321', 'Jugadora Secreta');
    $url = urlPlanillaFranja($this->partido);
    $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Jugadora Secreta');
    $respuesta = $this->actingAs($this->editor)->get($url)->assertOk();
    $respuesta->assertSee('25.000')->assertSee($this->local->nombre)
        ->assertDontSee('Jugadora Secreta')->assertDontSee('Privado')->assertDontSee('87654321')
        ->assertDontSee('2012-03-17')->assertDontSee('17/03/2012')
        ->assertDontSee('data-player-search', false)->assertDontSee('autosave', false)
        ->assertDontSee(route('planilla.actualizarJugadores'), false)
        ->assertDontSee(route('planilla.agregarJugador'), false)
        ->assertDontSee(route('planilla.actualizarGolesArbitros'), false)
        ->assertDontSee(route('planilla.confirmarGoles'), false);
    $polling = urlPollingFranja($respuesta->getContent());
    $this->get($polling)->assertOk()->assertDontSee('Jugadora Secreta')->assertDontSee('87654321');
    $this->post(route('planilla.actualizarJugadores'), [])->assertRedirect('/');
    $this->post(route('planilla.agregarJugador'), [])->assertRedirect('/');
    $this->post('/Panel/cobros/exenciones', [])->assertRedirect('/');
});

it('no afirma estar al día cuando falta la fecha del partido o de los cargos', function (string $caso): void {
    $local = crearEquipoCargos($this->contexto, 'Equipo fecha incierta');
    $visitante = crearEquipoCargos($this->contexto, 'Rival fecha incierta');
    $partido = partidoFranja($this->contexto, $local, $visitante, '2026-06-15 10:00:00');
    $anterior = partidoFranja($this->contexto, $local, $visitante, '2026-06-14 10:00:00');
    cargoFranja($this->contexto, $anterior, $local, $visitante, 12345);
    if ($caso === 'partido sin fecha' || $caso === 'partido horario legacy') {
        $partido->forceFill(['horario_datetime' => null, 'horario' => $caso === 'partido horario legacy' ? '10:00' : null])->save();
    } else {
        $anterior->forceFill(['horario_datetime' => null, 'horario' => $caso === 'cargo horario legacy' ? '10:00' : null])->save();
    }

    $respuesta = $this->actingAs($this->editor)->get(urlPlanillaFranja($partido))->assertOk();
    $html = $respuesta->getContent();
    $inicio = 'data-equipo-franja="'.$local->id.'"';
    $fin = 'data-equipo-franja="'.$visitante->id.'"';
    expect($html)->toContain($inicio)->toContain($fin);
    expect(Str::between($html, $inicio, $fin))->not->toContain('Al día');
    $actualizado = $this->get(urlPollingFranja($html))->assertOk()->getContent();
    expect($actualizado)->toContain($inicio)->toContain($fin);
    expect(Str::between($actualizado, $inicio, $fin))->not->toContain('Al día');
})->with(['partido sin fecha', 'partido horario legacy', 'cargo sin fecha', 'cargo horario legacy']);

it('limita deuda a hoy aunque el partido sea futuro y conserva el cobro del partido actual', function (): void {
    Carbon::setTestNow('2026-06-14 12:00:00');
    try {
        $cargoActual = cargoFranja($this->contexto, $this->partido, $this->local, $this->visitante, 25000);
        $admin = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk();
        $admin->assertSee('10.000')->assertDontSee('Debe $ 35.000')->assertDontSee('99.000')
            ->assertSee('data-cobro="pago"', false)->assertSee('data-cobro="exencion"', false)
            ->assertSee('&quot;id&quot;:'.$cargoActual->id, false);
        $this->actingAs($this->editor)->get(urlPlanillaFranja($this->partido))->assertOk()
            ->assertSee('10.000')->assertDontSee('15.000')->assertDontSee('25.000')->assertDontSee('99.000');
    } finally {
        Carbon::setTestNow();
    }
});

it('no mezcla jugadores de partido y final con el mismo identificador', function (): void {
    $final = new InstanciaFinal(crearDatosFinalCargos($this->contexto, $this->local, $this->visitante, ['horario' => '2026-06-15 10:00:00']));
    $final->forceFill(['id' => $this->partido->id])->save();
    jugadorFranja($this->partido, $this->local, '87654321', 'Jugador Regular');
    jugadorFranja($final, $this->local, '87654322', 'Jugador Final');
    $urlFinal = route('planilla.show', ['partidoId' => $final->id, 'idEdicion' => $final->idEdicion, 'tipoPartido' => 'instanciaFinal', 'horario' => 0]);

    $this->actingAs($this->editor)->get(urlPlanillaFranja($this->partido))->assertOk()
        ->assertDontSee('Jugador Final')->assertDontSee('Jugador Regular')->assertDontSee('87654321')->assertDontSee('87654322');
    $this->get($urlFinal)->assertOk()
        ->assertDontSee('Jugador Final')->assertDontSee('Jugador Regular')->assertDontSee('87654321')->assertDontSee('87654322');
    $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk()
        ->assertSee('Jugador Regular')->assertDontSee('Jugador Final')->assertDontSee('87654322');
    $this->get($urlFinal)->assertOk()->assertSee('Jugador Final')->assertDontSee('Jugador Regular')->assertDontSee('87654321');
});

it('exige usuario verificado tanto en la planilla como en el polling', function (): void {
    $url = urlPlanillaFranja($this->partido);
    $html = $this->actingAs($this->editor)->get($url)->assertOk()->getContent();
    $polling = urlPollingFranja($html);
    $this->editor->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->editor)->get($url)->assertRedirect(route('verification.notice'));
    $this->get($polling)->assertRedirect(route('verification.notice'));
    auth()->forgetGuards();
    $this->get($url)->assertNotFound();
    $this->get($polling)->assertNotFound();
});

it('cambia la firma del polling cuando se activan cobros sin cambiar cargos ni saldos', function (): void {
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);
    $this->actingAs($this->admin);
    $pagina = $this->get(urlPlanillaFranja($this->partido))->assertOk();
    $url = urlPollingFranja($pagina->getContent());
    $antes = $this->get($url)->assertOk()->assertDontSee('data-cobro="pago"', false)
        ->assertDontSee('data-cobro="exencion"', false)->getContent();
    preg_match('/data-firma="([^"]+)"/', $antes, $firmaAntes);
    expect($firmaAntes[1] ?? '')->not->toBeEmpty();

    $this->postJson('/Panel/cobros/activar', ['confirmar' => true])->assertSuccessful();
    $despues = $this->get($url)->assertOk()->assertSee('data-cobro="pago"', false)
        ->assertSee('data-cobro="exencion"', false)->getContent();
    preg_match('/data-firma="([^"]+)"/', $despues, $firmaDespues);
    expect($firmaDespues[1] ?? '')->not->toBeEmpty()->not->toBe($firmaAntes[1]);
    $estable = $this->get($url)->assertOk()->getContent();
    preg_match('/data-firma="([^"]+)"/', $estable, $firmaEstable);
    expect($firmaEstable[1] ?? '')->toBe($firmaDespues[1]);
});

it('actualiza la franja por polling sin entregar datos financieros amplios y protege el recurso', function (): void {
    $pagina = $this->actingAs($this->editor)->get(urlPlanillaFranja($this->partido))->assertOk();
    $url = urlPollingFranja($pagina->getContent());
    $this->get($url)->assertOk()->assertSee('25.000')->assertDontSee('99.000')
        ->assertDontSee('data-cobro="pago"', false)->assertDontSee('historial');

    $this->actingAs($this->admin)->postJson('/Panel/cobros/pagos', [
        'idEdicion' => $this->contexto['edicion']->id, 'idEquipo' => $this->local->id,
        'cargo_id' => $this->cargoAnterior->id, 'importe_ars' => 5000, 'medio' => 'efectivo',
        'clave_idempotencia' => (string) Str::uuid(),
    ])->assertSuccessful();
    $this->actingAs($this->editor)->get($url)->assertOk()->assertSee('20.000')->assertDontSee('25.000');
    $this->actingAs(crearUsuarioConRolCargos('regular'))->get($url)->assertRedirect('/');
    auth()->forgetGuards();
    $this->get($url)->assertNotFound();
});

it('renderiza la barra compacta fija de deudas y deja la franja en el flujo normal', function (): void {
    $html = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk()->getContent();

    expect($html)->toContain('data-franja-resumen-ancla')
        ->and($html)->toMatch('/class="sticky top-0 z-30 -mx-6 hidden h-0 lg:block" data-franja-resumen-ancla/')
        ->and($html)->toMatch('/<section class="-mx-6 mb-6 border-y border-gray-200 bg-white" aria-labelledby="cobro-planilla-titulo" data-franja-deudas>/')
        ->and($html)->toContain('overflow-x-clip')
        ->and($html)->not->toContain('min-h-screen w-full flex-col overflow-x-hidden');
});

it('muestra una sola tabla por equipo en el orden de la lista con asistencia y número primero', function (): void {
    jugadorFranja($this->partido, $this->local, '87654321', 'Jugadora Secreta');
    $html = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk()->getContent();

    preg_match('/<table class="w-full text-sm">.*?<\/thead>/s', $html, $encabezado);
    expect($encabezado)->not->toBeEmpty();
    $columnas = array_map(fn (string $th): string => trim(strip_tags($th)), preg_split('/<\/th>/', $encabezado[0], -1, PREG_SPLIT_NO_EMPTY));
    expect(array_values(array_filter($columnas)))->toBe(['Asistencia', 'Número', 'Jugador', 'Goles', 'Roja']);

    expect(substr_count($html, 'data-player-key="87654321"'))->toBe(1)
        ->and($html)->not->toContain('sm:hidden space-y-4');

    preg_match('/<tr data-player-item="local"\s+data-player-key="87654321".*?<\/tr>/s', $html, $fila);
    expect($fila)->not->toBeEmpty()
        ->and($fila[0])->toContain('Jugadora Secreta Privado')
        ->and($fila[0])->toContain('DNI 87.654.321 · Nac.&nbsp;17/03/2012')
        ->and($fila[0])->toContain('<input type="hidden" name="jugadores[87654321][dni]" value="87654321">')
        ->and($fila[0])->toContain('<input type="hidden" name="jugadores[87654321][fecha_nacimiento]"')
        ->and($fila[0])->not->toContain('readonly');
    expect(strpos($fila[0], '[asistencia]'))->toBeLessThan(strpos($fila[0], '[numero_camiseta]'))
        ->and(strpos($fila[0], '[numero_camiseta]'))->toBeLessThan(strpos($fila[0], '[goles]'))
        ->and(strpos($fila[0], '[goles]'))->toBeLessThan(strpos($fila[0], '[tarjeta_roja]'));
});

it('ofrece un buscador por DNI o nombre para cada equipo que marca la asistencia y pasa al número', function (): void {
    jugadorFranja($this->partido, $this->local, '87654321', 'Jugadora Secreta');
    $html = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk()->getContent();

    foreach (['local', 'visitante'] as $equipo) {
        expect($html)->toContain('data-player-search-input="'.$equipo.'" placeholder="DNI sin puntos o nombre"')
            ->and($html)->toContain('<label for="buscar-jugador-'.$equipo.'"')
            ->and($html)->toContain('data-player-search-eco="'.$equipo.'" aria-live="polite"')
            ->and($html)->toContain('data-player-attendance-count="'.$equipo.'"');
    }

    expect($html)->not->toContain('data-player-filter-toggle')
        ->and($html)->not->toContain('Elegí por cuál buscar')
        ->and($html)->toContain("asistencia.dispatchEvent(new Event('change', { bubbles: true }))")
        ->and($html)->toContain("sufijo === '[numero_camiseta]' && buscador")
        ->and($html)->toContain('data-player-repeated-note')
        ->and(substr_count($html, 'data-equipo-titulo'))->toBe(2)
        ->and($html)->toContain("onerror=\"this.onerror=null;this.src='".asset('fotos/Logo_Mitai_SinFondo.png')."';\"")
        ->and($html)->toContain('[&[data-search-oculto]]:!hidden')
        ->and($html)->toContain("item.toggleAttribute('data-search-oculto', activa && !coincide)");
});

it('informa al operario los jugadores que no se pudieron guardar', function (): void {
    jugadorFranja($this->partido, $this->local, '87654321', 'Jugadora Secreta');
    $html = $this->actingAs($this->admin)->get(urlPlanillaFranja($this->partido))->assertOk()->getContent();

    expect($html)->toContain('data-autosave-alerta role="alert" aria-live="assertive" hidden')
        ->and($html)->toContain('data-autosave-reintentar-todos')
        ->and($html)->toContain('window.addEventListener(\'beforeunload\'')
        ->and($html)->toContain("form.addEventListener('keydown', moverEntreJugadores)")
        ->and($html)->toMatch('/data-player-retry-save="87654321"\s+hidden class="rounded-md/')
        ->and($html)->not->toContain('class="hidden rounded-md');
});
