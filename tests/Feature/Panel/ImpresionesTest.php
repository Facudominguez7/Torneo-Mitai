<?php

// Feature tests del centro de Planillas e impresiones (T9b,
// panel-admin-redesign). Es un hub sobre las impresiones que ya existen: una
// tarjeta por impresión, cada una solo con los filtros que su ruta acepta de
// verdad. Las tarjetas se identifican con `data-impresion="<clave>"` y los
// atributos `data-print-*` que consume el JS de la página.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\User;

function crearAdminImpresiones(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array{edicion: Edicion, categoria: Categoria, fecha: Fecha}
 */
function crearContextoImpresiones(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Impresiones']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12 Impresiones']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha Impresiones 1']);

    return compact('edicion', 'categoria', 'fecha');
}

function crearPartidoConHorarioImpresiones(array $contexto, string $horario): Partido
{
    $local = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Local Imp', 'foto' => 'a.png']);
    $visitante = Equipo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Visita Imp', 'foto' => 'b.png']);
    $grupo = Grupo::create(['idCategoria' => $contexto['categoria']->id, 'idEdicion' => $contexto['edicion']->id, 'nombre' => 'Grupo Imp']);

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $grupo->id,
        'idEdicion' => $contexto['edicion']->id,
        'horario_datetime' => '2026-04-05 '.$horario.':00',
        'cancha' => 1,
        'jugado' => 0,
    ]);
}

/**
 * Devuelve el HTML de la tarjeta de impresión indicada (sin tarjetas anidadas).
 */
function tarjetaImpresion(string $html, string $clave): string
{
    preg_match('/<article\b[^>]*data-impresion="'.preg_quote($clave, '/').'".*?<\/article>/s', $html, $coincidencia);

    expect($coincidencia)->not->toBeEmpty("No se encontró la tarjeta data-impresion=\"{$clave}\"");

    return $coincidencia[0];
}

function verImpresiones($test, User $admin, array $contexto)
{
    return $test->actingAs($admin)->get(route('impresiones.index', ['idEdicion' => $contexto['edicion']->id]));
}

it('renderiza el encabezado y las tres secciones', function () {
    $contexto = crearContextoImpresiones();

    $response = verImpresiones($this, crearAdminImpresiones(), $contexto);

    $response->assertOk()
        ->assertSee('Planillas e impresiones')
        ->assertSee('Todo lo que se imprime para el día de partido, en un solo lugar.')
        ->assertSee('Día de partido')
        ->assertSee('Equipos y jugadores')
        ->assertSee('Posiciones e instancias finales');
});

it('la tarjeta del fixture imprime la edicion y acepta una fecha opcional', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $tarjeta = tarjetaImpresion($html, 'fixture');

    expect($tarjeta)
        ->toContain('data-print-url="'.route('admin.imprimir-fixture', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->toContain('data-print-param="idFecha"')
        ->toContain('<option value="'.$contexto['fecha']->id.'">Fecha Impresiones 1</option>')
        ->toContain('<label');
});

it('la planilla de arbitros acepta horario y avisa que usa la proxima fecha sin jugar', function () {
    $contexto = crearContextoImpresiones();
    crearPartidoConHorarioImpresiones($contexto, '10:00');

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $tarjeta = tarjetaImpresion($html, 'arbitros');

    expect($tarjeta)
        ->toContain('data-print-url="'.route('admin.imprimir-fixture-arbitros-partidos', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->toContain('data-print-param="horario"')
        ->toContain('<option value="10:00">10:00</option>')
        ->toContain('próxima fecha sin partidos jugados');
});

it('normaliza los horarios legacy sin duplicarlos con los de horario_datetime', function () {
    $contexto = crearContextoImpresiones();
    crearPartidoConHorarioImpresiones($contexto, '17:30');
    // Ediciones viejas: horario en texto ("17:30", "19.30", "9") y sin horario_datetime.
    foreach (['17:30', '19.30', '9'] as $horarioLegacy) {
        crearPartidoConHorarioImpresiones($contexto, '00:00')
            ->forceFill(['horario_datetime' => null, 'horario' => $horarioLegacy])
            ->save();
    }

    $tarjeta = tarjetaImpresion(verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent(), 'arbitros');
    preg_match_all('/<option value="([^"]+)"/', $tarjeta, $opciones);

    expect($opciones[1])->toBe(['09:00', '17:30', '19:30']);
});

it('ya no ofrece la planilla de cobro impresa', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();

    expect($html)->not->toContain('data-impresion="cobro"')
        ->and($html)->not->toContain('Planilla de cobro')
        ->and($html)->not->toContain('admin/planilla-cobro');
    $this->actingAs(crearAdminImpresiones())->get('/Panel/admin/planilla-cobro?idEdicion='.$contexto['edicion']->id)->assertNotFound();
});

it('las listas de buena fe aceptan una categoria opcional de la edicion', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $tarjeta = tarjetaImpresion($html, 'listas-buena-fe');

    expect($tarjeta)
        ->toContain('data-print-url="'.route('lista-buena-fe.imprimir-planillas', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->toContain('data-print-param="idCategoria"')
        ->toContain('<option value="'.$contexto['categoria']->id.'">Sub 12 Impresiones</option>');
});

it('las planillas de partido exigen una fecha y arrancan deshabilitadas', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $tarjeta = tarjetaImpresion($html, 'planillas-partido');

    $urlBase = route('lista-buena-fe.imprimir-planillas-fecha', ['idEdicion' => $contexto['edicion']->id, 'idFecha' => '__FECHA__']);

    expect($tarjeta)
        ->toContain('data-print-url="'.$urlBase.'"')
        ->toContain('data-print-placeholder="__FECHA__"')
        ->toContain('data-print-requerido')
        ->toContain('<option value="'.$contexto['fecha']->id.'">Fecha Impresiones 1</option>');

    expect(preg_match('/<button\b[^>]*data-print-url=[^>]*\bdisabled\b[^>]*>/s', $tarjeta))->toBe(1);
});

it('las tablas de posiciones y de instancias finales imprimen solo la edicion', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $posiciones = tarjetaImpresion($html, 'tabla-posiciones');
    $instancias = tarjetaImpresion($html, 'instancias-finales');

    expect($posiciones)
        ->toContain('data-print-url="'.route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->not->toContain('<select');
    expect($instancias)
        ->toContain('data-print-url="'.route('admin.imprimir-fixture-instancias-finales', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->not->toContain('<select');
});

it('la planilla de arbitros de finales acepta horario opcional', function () {
    $contexto = crearContextoImpresiones();
    crearPartidoConHorarioImpresiones($contexto, '09:00');

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();
    $tarjeta = tarjetaImpresion($html, 'arbitros-finales');

    expect($tarjeta)
        ->toContain('data-print-url="'.route('admin.imprimir-fixture-arbitros', ['idEdicion' => $contexto['edicion']->id]).'"')
        ->toContain('data-print-param="horario"')
        ->toContain('<option value="09:00">09:00</option>');
});

it('no ofrece tarjetas para features sin ruta', function () {
    $contexto = crearContextoImpresiones();

    $response = verImpresiones($this, crearAdminImpresiones(), $contexto);

    $response->assertOk()
        ->assertDontSee('Reporte de jugadores')
        ->assertDontSee('PDF masivo')
        ->assertDontSee('Descargar PDF');
});

it('muestra un estado vacio con direccion cuando no hay edicion', function () {
    $response = $this->actingAs(crearAdminImpresiones())->get(route('impresiones.index'));

    $response->assertOk()
        ->assertSee('Seleccioná una edición para imprimir')
        ->assertDontSee('data-impresion=', false);
});

it('agrega Planillas e impresiones al grupo Hoy del menu, marcado como activo', function () {
    $contexto = crearContextoImpresiones();

    $html = verImpresiones($this, crearAdminImpresiones(), $contexto)->getContent();

    expect($html)->toMatch('/href="'.preg_quote(route('impresiones.index'), '/').'(\?[^"]*)?"[^>]*aria-current="page"/');
});

it('un invitado no puede ver el centro de impresiones', function () {
    $this->get(route('impresiones.index'))->assertRedirect('/login');
});

it('un usuario sin rol admin no puede ver el centro de impresiones', function () {
    $usuario = User::factory()->create(['rol' => 'regular', 'email_verified_at' => now()]);

    $this->actingAs($usuario)->get(route('impresiones.index'))->assertRedirect('/');
});
