<?php

// Feature tests for the Estadisticas de jugadores module migration onto the
// shared x-ui.* components (T8a, panel-admin-redesign). The controller is
// untouched: this only re-skins the existing KPI counters, the per-edicion
// counts, the edicion + busqueda filters, the expandable per-edicion detail
// rows (kept with their original ids/JS hook) and pagination.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\User;

function crearAdminEstadisticasJugadoresMigration(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function crearContextoEstadisticasJugadoresMigration(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Estadisticas Migration']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Migration',
    ]);
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $atributos['nombreEquipo'] ?? 'Mitai Verde',
        'foto' => 'escudo.png',
    ]);
    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
    ]);

    $equipoRival = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => ($atributos['nombreEquipo'] ?? 'Mitai Verde').' Rival',
        'foto' => 'escudo.png',
    ]);
    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipoRival->id,
        'idCategoria' => $categoria->id,
    ]);

    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Migration',
    ]);
    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo Migration',
    ]);

    return compact('edicion', 'categoria', 'equipo', 'equipoRival', 'fecha', 'grupo');
}

function crearJugadorActivoEstadisticasMigration(array $contexto, string $dni, string $nombre, string $apellido): Jugador
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
        'idCategoria' => $contexto['categoria']->id,
        'fecha_nacimiento' => '2013-05-10',
        'fecha_alta' => now()->subDays(3),
        'activo' => true,
    ]);

    return $jugador;
}

// Crea un partido efectivamente disputado (#202) y la línea de planilla del
// jugador en ese partido: la asistencia solo cuenta si el partido se jugó de
// verdad y no fue adjudicado por «No vino».
function crearPartidoJugadoEstadisticasMigration(array $contexto, string $dni, int $goles = 0, int $tarjetasRojas = 0): void
{
    $partido = Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $contexto['equipo']->id,
        'idEquipoVisitante' => $contexto['equipoRival']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => 1,
        'golesEquipoVisitante' => 0,
        'cancha' => 1,
        'jugado' => 1,
        'horario_datetime' => '2026-06-01 09:00:00',
    ]);

    PlanillaJugador::create([
        'dni_jugador' => $dni,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $contexto['equipo']->id,
        'idCategoria' => $contexto['categoria']->id,
        'partido_id' => $partido->id,
        'partido_type' => Partido::class,
        'asistio' => true,
        'goles' => $goles,
        'tarjeta_roja' => $tarjetasRojas,
    ]);
}

// Carga $cantidad partidos jugados (asistio=true) para el mismo jugador, cada
// uno en un partido distinto (hay un unique de dni_jugador+partido_id
// +partido_type), para poder empujar su conteo de participaciones por encima
// del umbral de 5.
function crearVariosPartidosJugadosEstadisticasMigration(array $contexto, string $dni, int $cantidad): void
{
    foreach (range(1, $cantidad) as $numero) {
        crearPartidoJugadoEstadisticasMigration($contexto, $dni);
    }
}

it('muestra las tarjetas kpi que ya calcula el controlador con los valores reales', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();

    // Tres jugadores activos con conteos de participaciones reales distintos
    // entre sí y a los dos lados del umbral de 5, para que total (3),
    // menos_de_umbral (1) y umbral_o_mas (2) sean tres valores distinguibles entre
    // ellos: una clave equivocada o un contador cambiado en el markup no
    // puede pasar inadvertido si los tres números son distintos (revisión
    // R3-kpi-valores). El umbral es 5 o más desde la issue #202.
    crearJugadorActivoEstadisticasMigration($contexto, '41000001', 'Mateo', 'Duarte');
    crearVariosPartidosJugadosEstadisticasMigration($contexto, '41000001', 2);

    crearJugadorActivoEstadisticasMigration($contexto, '41000002', 'Bruno', 'Sosa');
    crearVariosPartidosJugadosEstadisticasMigration($contexto, '41000002', 6);

    crearJugadorActivoEstadisticasMigration($contexto, '41000003', 'Lucas', 'Cabrera');
    crearVariosPartidosJugadosEstadisticasMigration($contexto, '41000003', 7);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()->assertSee('Estadísticas de jugadores');

    $contenido = $response->getContent();

    // Cada valor atado a su propia etiqueta con una regex, no solo la
    // presencia de la etiqueta estática.
    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-gray-900">3<\/span>\s*<p[^>]*>Jugadores activos<\/p>/');
    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-amber-600">1<\/span>\s*<p[^>]*>Menos de 5<\/p>/');
    expect($contenido)->toMatch('/<span class="text-2xl font-bold text-emerald-600">2<\/span>\s*<p[^>]*>5 o más<\/p>/');
});

it('conserva el filtro de edicion y la busqueda por nombre apellido o dni', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000010', 'Bruno', 'Sosa');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000010', 1);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'sosa',
    ]));

    $response->assertOk()
        ->assertSee('id="idEdicion"', false)
        ->assertSee('name="busqueda"', false)
        ->assertSee('value="sosa"', false)
        ->assertSee('Sosa, Bruno')
        ->assertSee('DNI: 41000010');
});

it('conserva el detalle por edicion con sus ids y el hook de js', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000020', 'Lucas', 'Cabrera');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000020', 2);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $response->assertOk()
        ->assertSee('id="detalles-41000020"', false)
        ->assertSee('id="icon-41000020"', false)
        ->assertSee("toggleDetalles('detalles-41000020')", false)
        ->assertSee('function toggleDetalles(id)', false)
        ->assertSee($contexto['edicion']->nombre);
});

it('conserva el conteo por edicion como chip navegable', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();
    crearJugadorActivoEstadisticasMigration($contexto, '41000030', 'Santino', 'Ortiz');
    crearPartidoJugadoEstadisticasMigration($contexto, '41000030', 0);

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $hrefEdicionActiva = e(route('estadisticas-jugadores', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee($hrefEdicionActiva, false)
        ->assertSee($contexto['edicion']->nombre);

    // aria-current="page" atado al <a> del chip de la edición activa, no a
    // cualquier otro elemento de la página que también lo emita (revisión
    // R3-aria-current-ambiguo).
    expect($response->getContent())->toMatch('/<a href="'.preg_quote($hrefEdicionActiva, '/').'"\s+aria-current="page"/');
});

it('renderiza el estado vacio especifico de busqueda sin resultados', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'Inexistente',
    ]));

    $response->assertOk()
        ->assertSee('No se encontraron jugadores')
        ->assertSee('No hay jugadores que coincidan con la búsqueda "Inexistente".', false);
});

it('conserva la paginacion existente con el link a la pagina dos y el filtro en su query string', function () {
    $admin = crearAdminEstadisticasJugadoresMigration();
    $contexto = crearContextoEstadisticasJugadoresMigration();

    // 25 jugadores activos son más de una página (paginate(20) en el
    // controlador): tiene que existir un link a la página 2. Se agrega
    // además una búsqueda que matchea a los 25 (por apellido), para probar
    // que el link de paginación conserva el filtro en su query string, no
    // solo la palabra "Mostrando" que igual aparece con una sola página
    // (revisión R3-paginacion-debil).
    foreach (range(1, 25) as $i) {
        $dni = (string) (41100000 + $i);
        crearJugadorActivoEstadisticasMigration($contexto, $dni, 'Jugador'.$i, 'Comun');
        crearPartidoJugadoEstadisticasMigration($contexto, $dni, 1);
    }

    $response = $this->actingAs($admin)->get(route('estadisticas-jugadores', [
        'idEdicion' => $contexto['edicion']->id,
        'busqueda' => 'Comun',
    ]));

    $response->assertOk()->assertSee('Mostrando');

    $contenido = $response->getContent();

    preg_match('/<a[^>]*href="([^"]*page=2[^"]*)"/', $contenido, $coincidencias);

    expect($coincidencias[1] ?? null)->not->toBeNull()
        ->and(html_entity_decode($coincidencias[1] ?? ''))
        ->toContain('idEdicion='.$contexto['edicion']->id)
        ->toContain('busqueda=Comun');
});
