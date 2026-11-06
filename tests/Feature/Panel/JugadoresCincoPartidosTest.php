<?php

// Pruebas de la issue #202: "jugadores con 5 o más partidos realmente
// disputados". La cifra del dashboard y el listado de estadísticas salen del
// mismo servicio, así que cada prueba mira la página real (contadores,
// tabla y enlace de paginación) sobre fixtures con partidos e instancias
// finales de verdad, nunca con ids sueltos en la planilla.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function crearAdminCincoPartidos(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

/**
 * Contexto mínimo (edición, categoría, fecha, grupo, dos equipos y una fase)
 * para poder crear partidos e instancias finales con claves foráneas reales.
 *
 * @return array{edicion: Edicion, categoria: Categoria, fecha: Fecha, grupo: Grupo, equipo: Equipo, rival: Equipo, fase: Fase}
 */
function crearContextoCincoPartidos(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Cinco Partidos']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 13',
    ]);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Cinco',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Cinco',
    ]);

    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreEquipo'] ?? 'Mitai Cinco',
        'foto' => 'escudo.png',
    ]);
    $rival = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreRival'] ?? 'Mitai Rival Cinco',
        'foto' => 'escudo.png',
    ]);

    foreach ([$equipo, $rival] as $equipoDelContexto) {
        EquipoEdicion::create([
            'idEdicion' => $edicion->id,
            'idEquipo' => $equipoDelContexto->id,
            'idCategoria' => $categoria->id,
        ]);
    }

    $fase = Fase::create(['nombre' => 'Final']);

    return compact('edicion', 'categoria', 'fecha', 'grupo', 'equipo', 'rival', 'fase');
}

function crearCategoriaCincoPartidos(array $contexto, string $nombre): Categoria
{
    return Categoria::create([
        'idEdicion' => $contexto['edicion']->id,
        'nombreCategoria' => $nombre,
    ]);
}

function crearEquipoCincoPartidos(array $contexto, Categoria $categoria, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    return $equipo;
}

function crearJugadorCincoPartidos(array $contexto, string $dni, string $nombre, string $apellido, ?Categoria $categoria = null): Jugador
{
    $jugador = Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
    ]);

    ListaBuenaFe::create([
        'dni_jugador' => $dni,
        'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => ($categoria ?? $contexto['categoria'])->id,
        'fecha_nacimiento' => '2012-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => true,
    ]);

    return $jugador;
}

function crearPartidoCincoPartidos(array $contexto, array $atributos = []): Partido
{
    return Partido::create([
        'idCategoria' => ($atributos['categoria'] ?? $contexto['categoria'])->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $contexto['equipo']->id,
        'idEquipoVisitante' => $contexto['rival']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $atributos['golesEquipoLocal'] ?? 1,
        'golesEquipoVisitante' => $atributos['golesEquipoVisitante'] ?? 0,
        'cancha' => 1,
        'jugado' => $atributos['jugado'] ?? 1,
        'ganador_por_no_vino' => $atributos['ganador_por_no_vino'] ?? null,
        'horario_datetime' => '2026-06-01 09:00:00',
    ]);
}

function crearInstanciaFinalCincoPartidos(array $contexto, ?int $golesLocal, ?int $golesVisitante): InstanciaFinal
{
    return InstanciaFinal::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $contexto['fase']->id,
        'idEquipoLocal' => $contexto['equipo']->id,
        'idEquipoVisitante' => $contexto['rival']->id,
        'horario' => '2026-06-10 10:00:00',
        'cancha' => '1',
        'golesEquipoLocal' => $golesLocal,
        'golesEquipoVisitante' => $golesVisitante,
    ]);
}

function registrarParticipacionCincoPartidos(array $contexto, string $dni, Partido|InstanciaFinal $partido, ?Categoria $categoria = null): PlanillaJugador
{
    return PlanillaJugador::create([
        'dni_jugador' => $dni,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idCategoria' => ($categoria ?? $contexto['categoria'])->id,
        'partido_id' => $partido->id,
        'partido_type' => $partido::class,
        'asistio' => true,
    ]);
}

/**
 * Carga $cantidad participaciones en partidos efectivamente disputados.
 */
function crearParticipacionesCincoPartidos(array $contexto, string $dni, int $cantidad, ?Categoria $categoria = null): void
{
    foreach (range(1, $cantidad) as $numero) {
        registrarParticipacionCincoPartidos(
            $contexto,
            $dni,
            crearPartidoCincoPartidos($contexto, $categoria ? ['categoria' => $categoria] : []),
            $categoria
        );
    }
}

/**
 * Cantidad de partidos que el listado muestra en la fila del jugador. Devuelve
 * null cuando el jugador no aparece en la página.
 */
function partidosMostradosCincoPartidos(string $contenido, string $dni): ?int
{
    $patron = '/DNI: '.preg_quote($dni, '/').'.*?text-xl font-bold text-brand-navy">(\d+)</s';

    if (! preg_match($patron, $contenido, $coincidencias)) {
        return null;
    }

    return (int) $coincidencias[1];
}

it('no cuenta al jugador que solo tiene cuatro participaciones reales', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    crearJugadorCincoPartidos($contexto, '41200001', 'Mateo', 'Duarte');
    crearParticipacionesCincoPartidos($contexto, '41200001', 4);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $contenido = $response->getContent();

    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-gray-900">1<\/span>\s*<p[^>]*>Jugadores activos<\/p>/')
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-amber-600">1<\/span>\s*<p[^>]*>Menos de 5<\/p>/')
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">0<\/span>\s*<p[^>]*>5 o más<\/p>/')
        ->and(partidosMostradosCincoPartidos($contenido, '41200001'))->toBe(4);

    $responseFiltrada = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'umbral' => 'cinco',
    ]));

    $responseFiltrada->assertOk()
        ->assertDontSee('DNI: 41200001')
        ->assertSee('No hay jugadores con 5 o más participaciones');
});

it('cuenta al jugador con exactamente cinco participaciones y reemplaza las etiquetas ambiguas', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    crearJugadorCincoPartidos($contexto, '41200010', 'Bruno', 'Sosa');
    crearParticipacionesCincoPartidos($contexto, '41200010', 5);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $contenido = $response->getContent();

    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-amber-600">0<\/span>\s*<p[^>]*>Menos de 5<\/p>/')
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">1<\/span>\s*<p[^>]*>5 o más<\/p>/')
        ->and(partidosMostradosCincoPartidos($contenido, '41200010'))->toBe(5);

    $this->actingAs($admin)->get(route('estadisticas-jugadores', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Con 5 partidos o menos')
        ->assertDontSee('Con más de 5 partidos');

    $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'umbral' => 'cinco',
    ]))->assertOk()
        ->assertSee('Sosa, Bruno')
        ->assertSee('DNI: 41200010');
});

it('cuenta al jugador con seis participaciones reales', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    crearJugadorCincoPartidos($contexto, '41200020', 'Lucas', 'Cabrera');
    crearParticipacionesCincoPartidos($contexto, '41200020', 6);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $contenido = $response->getContent();

    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">1<\/span>\s*<p[^>]*>5 o más<\/p>/')
        ->and(partidosMostradosCincoPartidos($contenido, '41200020'))->toBe(6);
});

it('no cuenta participaciones de un partido adjudicado por no vino', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    crearJugadorCincoPartidos($contexto, '41200030', 'Santino', 'Ortiz');
    crearParticipacionesCincoPartidos($contexto, '41200030', 4);

    // 3-0 administrativo: tiene jugado = 1 por efecto en la tabla de
    // posiciones, pero no se jugó físicamente (S3).
    $adjudicado = crearPartidoCincoPartidos($contexto, [
        'golesEquipoLocal' => 3,
        'golesEquipoVisitante' => 0,
        'ganador_por_no_vino' => $contexto['equipo']->id,
    ]);
    registrarParticipacionCincoPartidos($contexto, '41200030', $adjudicado);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $contenido = $response->getContent();

    expect(partidosMostradosCincoPartidos($contenido, '41200030'))->toBe(4)
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">0<\/span>\s*<p[^>]*>5 o más<\/p>/');

    $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'umbral' => 'cinco',
    ]))->assertOk()->assertDontSee('DNI: 41200030');
});

it('no cuenta participaciones de un partido que todavia no se jugo', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    crearJugadorCincoPartidos($contexto, '41200040', 'Thiago', 'Benitez');
    crearParticipacionesCincoPartidos($contexto, '41200040', 4);

    $sinJugar = crearPartidoCincoPartidos($contexto, [
        'golesEquipoLocal' => null,
        'golesEquipoVisitante' => null,
        'jugado' => 0,
    ]);
    registrarParticipacionCincoPartidos($contexto, '41200040', $sinJugar);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();

    expect(partidosMostradosCincoPartidos($response->getContent(), '41200040'))->toBe(4);
});

it('cuenta una instancia final solo cuando tiene los dos resultados cargados', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();

    crearJugadorCincoPartidos($contexto, '41200050', 'Bautista', 'Molina');
    crearParticipacionesCincoPartidos($contexto, '41200050', 4);
    registrarParticipacionCincoPartidos(
        $contexto,
        '41200050',
        crearInstanciaFinalCincoPartidos($contexto, 2, 1)
    );

    crearJugadorCincoPartidos($contexto, '41200051', 'Dante', 'Ferrari');
    crearParticipacionesCincoPartidos($contexto, '41200051', 4);
    registrarParticipacionCincoPartidos(
        $contexto,
        '41200051',
        crearInstanciaFinalCincoPartidos($contexto, 2, null)
    );

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();
    $contenido = $response->getContent();

    expect(partidosMostradosCincoPartidos($contenido, '41200050'))->toBe(5)
        ->and(partidosMostradosCincoPartidos($contenido, '41200051'))->toBe(4)
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-amber-600">1<\/span>\s*<p[^>]*>Menos de 5<\/p>/')
        ->and($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">1<\/span>\s*<p[^>]*>5 o más<\/p>/');
});

it('no mezcla planillas de partidos e instancias finales que comparten id', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();

    // El id de un partido y el de una instancia final salen de tablas
    // distintas: se busca un número libre en ambas para poder crear un
    // partido y una final con el mismo id y comprobar que la planilla no se
    // cruza de tipo.
    $idCompartido = max(
        (int) DB::table('partidos')->max('id'),
        (int) DB::table('instancias_finales')->max('id')
    ) + 1;

    DB::table('partidos')->insert([
        'id' => $idCompartido,
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $contexto['equipo']->id,
        'idEquipoVisitante' => $contexto['rival']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-06-01 09:00:00',
        'public_token' => (string) Str::uuid(),
    ]);

    DB::table('instancias_finales')->insert([
        'id' => $idCompartido,
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => $contexto['fase']->id,
        'idEquipoLocal' => $contexto['equipo']->id,
        'idEquipoVisitante' => $contexto['rival']->id,
        'horario' => '2026-06-10 10:00:00',
        'cancha' => '1',
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 1,
        'public_token' => (string) Str::uuid(),
    ]);

    crearJugadorCincoPartidos($contexto, '41200060', 'Valentino', 'Acosta');
    registrarParticipacionCincoPartidos($contexto, '41200060', Partido::findOrFail($idCompartido));
    registrarParticipacionCincoPartidos($contexto, '41200060', InstanciaFinal::findOrFail($idCompartido));

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk();

    // Dos partidos distintos con el mismo id: ni se duplican ni se pierden.
    expect(partidosMostradosCincoPartidos($response->getContent(), '41200060'))->toBe(2);
});

it('aisla el conteo de participaciones por edicion', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    $otraEdicion = crearContextoCincoPartidos(['nombreEdicion' => 'Edicion Cinco Anterior']);

    crearJugadorCincoPartidos($contexto, '41200070', 'Franco', 'Quiroga');
    ListaBuenaFe::create([
        'dni_jugador' => '41200070',
        'idEquipo' => $otraEdicion['equipo']->id,
        'idEdicion' => $otraEdicion['edicion']->id,
        'idCategoria' => $otraEdicion['categoria']->id,
        'fecha_nacimiento' => '2012-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => true,
    ]);

    crearParticipacionesCincoPartidos($contexto, '41200070', 5);
    crearParticipacionesCincoPartidos($otraEdicion, '41200070', 2);

    $responseActual = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $responseActual->assertOk();
    expect(partidosMostradosCincoPartidos($responseActual->getContent(), '41200070'))->toBe(5);

    $responseAnterior = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $otraEdicion['edicion']->id,
    ]));

    $responseAnterior->assertOk();
    $contenidoAnterior = $responseAnterior->getContent();

    expect(partidosMostradosCincoPartidos($contenidoAnterior, '41200070'))->toBe(2)
        ->and($contenidoAnterior)->toMatch('/<span class="text-2xl font-bold text-emerald-600">0<\/span>\s*<p[^>]*>5 o más<\/p>/');
});

it('aplica el filtro de categoria en los contadores y en el listado', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();
    $otraCategoria = crearCategoriaCincoPartidos($contexto, 'Sub 17');

    crearJugadorCincoPartidos($contexto, '41200080', 'Ignacio', 'Peralta');
    crearParticipacionesCincoPartidos($contexto, '41200080', 5);

    $equipoOtraCategoria = crearEquipoCincoPartidos($contexto, $otraCategoria, 'Mitai Sub 17');
    Jugador::create(['dni' => '41200081', 'nombre' => 'Tobias', 'apellido' => 'Ledesma']);
    ListaBuenaFe::create([
        'dni_jugador' => '41200081',
        'idEquipo' => $equipoOtraCategoria->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $otraCategoria->id,
        'fecha_nacimiento' => '2011-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => true,
    ]);
    crearParticipacionesCincoPartidos($contexto, '41200081', 3);
    crearParticipacionesCincoPartidos($contexto, '41200081', 3, $otraCategoria);

    $responseSinFiltro = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $responseSinFiltro->assertOk();
    expect($responseSinFiltro->getContent())->toMatch('/<span class="text-2xl font-bold text-emerald-600">2<\/span>\s*<p[^>]*>5 o más<\/p>/');

    $responseFiltrada = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]));

    $responseFiltrada->assertOk()
        ->assertSee('id="idCategoria"', false)
        ->assertSee('name="idCategoria"', false);

    $contenidoFiltrado = $responseFiltrada->getContent();

    expect($contenidoFiltrado)->toMatch('/<span class="text-2xl font-bold text-gray-900">1<\/span>\s*<p[^>]*>Jugadores activos<\/p>/')
        ->and($contenidoFiltrado)->toMatch('/<span class="text-2xl font-bold text-emerald-600">1<\/span>\s*<p[^>]*>5 o más<\/p>/')
        ->and(partidosMostradosCincoPartidos($contenidoFiltrado, '41200080'))->toBe(5);
});

it('conserva los filtros de edicion busqueda categoria y umbral en la paginacion', function () {
    $admin = crearAdminCincoPartidos();
    $contexto = crearContextoCincoPartidos();

    foreach (range(1, 21) as $i) {
        $dni = (string) (41210000 + $i);
        crearJugadorCincoPartidos($contexto, $dni, 'Jugador'.$i, 'Comun');
        crearParticipacionesCincoPartidos($contexto, $dni, 5);
    }

    // Un jugador más que matchea la búsqueda pero no llega al umbral: queda
    // fuera del listado filtrado y del total, aunque la paginación conserve
    // todos los parámetros en el enlace de la página 2.
    crearJugadorCincoPartidos($contexto, '41210099', 'Jugador Fuera', 'Comun');
    crearParticipacionesCincoPartidos($contexto, '41210099', 4);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'Comun',
        'idCategoria' => $contexto['categoria']->id,
        'umbral' => 'cinco',
    ]));

    $response->assertOk()->assertSee('Mostrando');

    expect($response->getContent())
        ->toMatch('/de\s*<span class="font-semibold">21<\/span>\s*jugadores/');

    preg_match('/<a[^>]*href="([^"]*page=2[^"]*)"/', $response->getContent(), $coincidencias);
    $enlacePaginaDos = html_entity_decode($coincidencias[1] ?? '');

    expect($enlacePaginaDos)->not->toBeEmpty()
        ->and($enlacePaginaDos)->toContain('idEdicion='.$contexto['edicion']->id)
        ->and($enlacePaginaDos)->toContain('busqueda=Comun')
        ->and($enlacePaginaDos)->toContain('idCategoria='.$contexto['categoria']->id)
        ->and($enlacePaginaDos)->toContain('umbral=cinco');
});
