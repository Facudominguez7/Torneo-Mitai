<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\GolArbitroReportado;
use App\Models\Grupo;
use App\Models\InstanciaFinal;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\PlanillaJugador;
use App\Models\TablaGoleador;
use App\Models\TablaPosicion;
use App\Models\User;
use Illuminate\Support\Facades\Log;

function crearAdminPartidos(): User
{
    return User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);
}

function tokenDePrueba(): string
{
    return 'token-csrf-de-prueba';
}

/**
 * El contenedor de testing exporta APP_ENV=local y el middleware CSRF valida
 * en consecuencia; se inyecta el token explicitamente para las peticiones mutantes.
 */
function cabecerasCsrf(): array
{
    return ['X-CSRF-TOKEN' => tokenDePrueba()];
}

/**
 * Extrae todos los href del HTML renderizado para inspeccionar enlaces por
 * destino semantico en lugar de contar clases CSS fragiles.
 *
 * @return list<string>
 */
function hrefsDelContenido(string $contenido): array
{
    preg_match_all('/href="([^"]+)"/', $contenido, $coincidencias);

    return array_map(
        fn (string $href): string => html_entity_decode($href, ENT_QUOTES | ENT_HTML5),
        $coincidencias[1],
    );
}

/**
 * Devuelve el primer href cuyo camino contiene el fragmento indicado, o null.
 */
function hrefQueContenga(string $contenido, string $fragmento): ?string
{
    return collect(hrefsDelContenido($contenido))->first(
        fn (string $href): bool => str_contains($href, $fragmento),
    );
}

/**
 * Devuelve el bloque HTML completo (apertura a cierre) del primer enlace o
 * boton que contiene el texto indicado, o null si no existe.
 */
function bloqueDeAccion(string $contenido, string $texto): ?string
{
    preg_match_all('/<(a|button)\b[^>]*>.*?<\/\1>/s', $contenido, $bloques);

    $bloqueEncontrado = collect($bloques[0] ?? [])->first(
        fn (string $bloque): bool => str_contains($bloque, $texto),
    );

    return is_string($bloqueEncontrado) ? $bloqueEncontrado : null;
}

function crearContextoPartidos(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Partidos']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function crearContextoInterzonal(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Interzonal']);

    // El recalculo legacy de golesContra reescribe ediciones 1..3 a idEdicion=0;
    // se fija un id fuera de ese rango para que el escenario sea determinista.
    $edicion->id = 100;
    $edicion->save();

    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupoA = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $grupoB = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo B']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);

    $grupo = $grupoA;

    return compact('edicion', 'categoria', 'grupo', 'grupoA', 'grupoB', 'fecha');
}

function crearEquipoPartido(array $contexto, string $nombre): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'golesContra' => 0,
    ]);

    return $equipo;
}

function crearPartidoRegular(array $contexto, Equipo $local, Equipo $visitante, array $atributos = []): Partido
{
    return Partido::create(array_merge([
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => 1,
        'jugado' => 0,
    ], $atributos));
}

function crearPlanillaDePartido(Partido $partido, string $dni, int $idEquipo, int $goles): PlanillaJugador
{
    return PlanillaJugador::create([
        'dni_jugador' => $dni,
        'idEquipo' => $idEquipo,
        'idCategoria' => $partido->idCategoria,
        'partido_id' => $partido->id,
        'partido_type' => Partido::class,
        'idEdicion' => $partido->idEdicion,
        'numero_camiseta' => 10,
        'goles' => $goles,
        'asistio' => true,
    ]);
}

function registrarResultadoPartido(Partido $partido, int $golesLocal, int $golesVisitante): void
{
    $partido->update([
        'golesEquipoLocal' => $golesLocal,
        'golesEquipoVisitante' => $golesVisitante,
        'jugado' => 1,
    ]);
}

it('permite cambiar equipos y campos en un partido sin actividad', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $nuevoLocal = crearEquipoPartido($contexto, 'Tafi Viejo');
    $partido = crearPartidoRegular($contexto, $local, $visitante);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->patch(route('partido.update', $partido->id), [
            'idFechas' => $contexto['fecha']->id,
            'idEquipoLocal' => $nuevoLocal->id,
            'idEquipoVisitante' => $local->id,
            'horario_datetime' => '2026-04-12T16:30',
            'cancha' => '2',
            'retorno_idCategoria' => $contexto['categoria']->id,
            'retorno_idGrupo' => $contexto['grupo']->id,
            'retorno_idFecha' => $contexto['fecha']->id,
        ]);

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));
    $response->assertSessionHas('status', 'Partido actualizado con éxito.');

    $this->assertDatabaseHas('partidos', [
        'id' => $partido->id,
        'idEquipoLocal' => $nuevoLocal->id,
        'idEquipoVisitante' => $local->id,
        'cancha' => 2,
    ]);
});

it('bloquea el cambio de equipos cuando el partido ya se jugo', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $otroEquipo = crearEquipoPartido($contexto, 'Tafi Viejo');
    $partido = crearPartidoRegular($contexto, $local, $visitante, ['jugado' => 1]);
    registrarResultadoPartido($partido, 2, 1);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->patch(route('partido.update', $partido->id), [
            'idFechas' => $contexto['fecha']->id,
            'idEquipoLocal' => $otroEquipo->id,
            'idEquipoVisitante' => $local->id,
            'horario_datetime' => '2026-04-12T16:30',
            'cancha' => '9',
        ]);

    $response->assertSessionHasErrors(['idEquipoLocal', 'idEquipoVisitante']);

    $this->assertDatabaseHas('partidos', [
        'id' => $partido->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'cancha' => 1,
    ]);
});

it('bloquea el cambio de equipos cuando el partido tiene planillas aunque no este jugado', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $otroEquipo = crearEquipoPartido($contexto, 'Tafi Viejo');
    $partido = crearPartidoRegular($contexto, $local, $visitante);
    Jugador::create(['dni' => '30111222', 'nombre' => 'Base', 'apellido' => 'Convocado', 'goles_totales' => 0]);
    crearPlanillaDePartido($partido, (string) 30111222, $local->id, 0);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->patch(route('partido.update', $partido->id), [
            'idFechas' => $contexto['fecha']->id,
            'idEquipoLocal' => $otroEquipo->id,
            'idEquipoVisitante' => $local->id,
            'horario_datetime' => '2026-04-12T16:30',
            'cancha' => '9',
        ]);

    $response->assertSessionHasErrors(['idEquipoLocal', 'idEquipoVisitante']);
    $this->assertDatabaseHas('partidos', ['id' => $partido->id, 'idEquipoLocal' => $local->id]);
});

it('bloquea el cambio de equipos cuando el partido tiene goles confirmados sin estar jugado', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $otroEquipo = crearEquipoPartido($contexto, 'Tafi Viejo');
    $partido = crearPartidoRegular($contexto, $local, $visitante);
    $partido->update(['goles_confirmados_at' => now()]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->patch(route('partido.update', $partido->id), [
            'idFechas' => $contexto['fecha']->id,
            'idEquipoLocal' => $otroEquipo->id,
            'idEquipoVisitante' => $local->id,
            'horario_datetime' => '2026-04-12T16:30',
            'cancha' => '9',
        ]);

    $response->assertSessionHasErrors(['idEquipoLocal', 'idEquipoVisitante']);
    $this->assertDatabaseHas('partidos', [
        'id' => $partido->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'jugado' => 0,
    ]);
});

it('permite editar otros campos sin reenviar equipos en un partido jugado', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $partido = crearPartidoRegular($contexto, $local, $visitante, ['jugado' => 1]);
    registrarResultadoPartido($partido, 2, 1);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->patch(route('partido.update', $partido->id), [
            'idFechas' => $contexto['fecha']->id,
            'horario_datetime' => '2026-04-12T16:30',
            'cancha' => '7',
        ]);

    $response->assertSessionHas('status', 'Partido actualizado con éxito.');

    $this->assertDatabaseHas('partidos', [
        'id' => $partido->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'cancha' => 7,
    ]);
});

it('al eliminar un partido jugado revierte posiciones, historial, goles y conserva datos manuales', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $rivalInstancia = crearEquipoPartido($contexto, 'Tafi Viejo');
    $partido = crearPartidoRegular($contexto, $local, $visitante);
    registrarResultadoPartido($partido, 2, 1);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesFavor' => 2,
        'golesContra' => 1,
        'diferenciaGoles' => 1,
        'jugado' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
        'puntos' => 3,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $visitante->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesFavor' => 1,
        'golesContra' => 2,
        'diferenciaGoles' => -1,
        'jugado' => 1,
        'ganado' => 0,
        'perdido' => 1,
        'empatado' => 0,
        'puntos' => 0,
    ]);

    $local->update(['partidos_jugados' => 1, 'victorias' => 1, 'puntos' => 3, 'golesFavor' => 2, 'golesContra' => 1, 'diferenciaGoles' => 1]);
    $visitante->update(['partidos_jugados' => 1, 'derrotas' => 1, 'golesFavor' => 1, 'golesContra' => 2, 'diferenciaGoles' => -1]);

    $goleadorPartido = Jugador::create(['dni' => '30111222', 'nombre' => 'Base', 'apellido' => 'Goleador', 'goles_totales' => 5]);
    crearPlanillaDePartido($partido, '30111222', $local->id, 2);
    TablaGoleador::create(['dni_jugador' => '30111222', 'cantidadGoles' => 5, 'idEquipo' => $local->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]);

    Jugador::create(['dni' => '30333444', 'nombre' => 'Suplente', 'apellido' => 'SinGoles', 'goles_totales' => 0]);
    crearPlanillaDePartido($partido, '30333444', $visitante->id, 0);
    TablaGoleador::create(['dni_jugador' => '30333444', 'cantidadGoles' => 0, 'idEquipo' => $visitante->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => Partido::class,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $local->id,
        'goles_reportados' => 2,
    ]);

    $instancia = InstanciaFinal::create([
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $rivalInstancia->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idFase' => 1,
        'horario' => '2026-05-10 12:00:00',
        'cancha' => 'Cancha 3',
        'golesEquipoLocal' => 0,
        'golesEquipoVisitante' => 2,
    ]);

    GolArbitroReportado::create([
        'partido_id' => $instancia->id,
        'partido_type' => InstanciaFinal::class,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $rivalInstancia->id,
        'goles_reportados' => 3,
    ]);

    Jugador::create(['dni' => '30555666', 'nombre' => 'Delantero', 'apellido' => 'Instancia', 'goles_totales' => 4]);

    PlanillaJugador::create([
        'dni_jugador' => '30555666',
        'idEquipo' => $rivalInstancia->id,
        'idCategoria' => $contexto['categoria']->id,
        'partido_id' => $instancia->id,
        'partido_type' => InstanciaFinal::class,
        'idEdicion' => $contexto['edicion']->id,
        'numero_camiseta' => 9,
        'goles' => 4,
        'asistio' => true,
    ]);

    EquipoEdicion::where('idEquipo', $local->id)->update(['golesContra' => 3]);
    EquipoEdicion::where('idEquipo', $visitante->id)->update(['golesContra' => 2]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->delete(route('partido.destroy', array_filter([
            'partido' => $partido->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
            'idFecha' => $contexto['fecha']->id,
        ])));

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));
    $response->assertSessionHas('status', 'Partido eliminado con éxito.');

    $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);
    $this->assertDatabaseMissing('planilla_jugadores', ['partido_id' => $partido->id, 'partido_type' => Partido::class]);
    $this->assertDatabaseMissing('goles_arbitros_reportados', ['partido_id' => $partido->id, 'partido_type' => Partido::class]);

    $this->assertDatabaseHas('tabla_posiciones', ['idEquipo' => $local->id, 'jugado' => 0, 'ganado' => 0, 'puntos' => 0, 'golesFavor' => 0, 'golesContra' => 0]);
    $this->assertDatabaseHas('tabla_posiciones', ['idEquipo' => $visitante->id, 'jugado' => 0, 'perdido' => 0, 'puntos' => 0, 'golesFavor' => 0, 'golesContra' => 0]);

    $this->assertDatabaseHas('equipos', ['id' => $local->id, 'partidos_jugados' => 0, 'victorias' => 0, 'puntos' => 0, 'golesFavor' => 0, 'golesContra' => 0]);
    $this->assertDatabaseHas('equipos', ['id' => $visitante->id, 'partidos_jugados' => 0, 'derrotas' => 0, 'golesFavor' => 0, 'golesContra' => 0]);

    $this->assertDatabaseHas('jugadores', ['dni' => '30111222', 'goles_totales' => 3]);
    $this->assertDatabaseHas('tabla_goleadores', ['dni_jugador' => '30111222', 'cantidadGoles' => 3]);
    $this->assertDatabaseHas('tabla_goleadores', ['dni_jugador' => '30333444', 'cantidadGoles' => 0]);

    $this->assertDatabaseHas('equipo_ediciones', ['idEquipo' => $local->id, 'golesContra' => 2]);
    $this->assertDatabaseHas('equipo_ediciones', ['idEquipo' => $visitante->id, 'golesContra' => 0]);

    $this->assertDatabaseHas('instancias_finales', ['id' => $instancia->id, 'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 2]);
    $this->assertDatabaseHas('goles_arbitros_reportados', ['partido_id' => $instancia->id, 'partido_type' => InstanciaFinal::class]);
    $this->assertDatabaseHas('planilla_jugadores', ['partido_id' => $instancia->id, 'partido_type' => InstanciaFinal::class, 'goles' => 4]);
    $this->assertDatabaseHas('jugadores', ['dni' => '30555666', 'goles_totales' => 4]);
});

it('al fallar la eliminacion revierte todo y avisa con un mensaje', function () {
    Log::spy();
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $partido = crearPartidoRegular($contexto, $local, $visitante);
    registrarResultadoPartido($partido, 2, 1);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesFavor' => 2,
        'golesContra' => 1,
        'diferenciaGoles' => 1,
        'jugado' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
        'puntos' => 3,
    ]);

    Jugador::create(['dni' => '30111222', 'nombre' => 'Base', 'apellido' => 'Goleador', 'goles_totales' => 2]);
    $planilla = crearPlanillaDePartido($partido, '30111222', $local->id, 2);
    TablaGoleador::create(['dni_jugador' => '30111222', 'cantidadGoles' => 2, 'idEquipo' => $local->id, 'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id]);

    GolArbitroReportado::create([
        'partido_id' => $partido->id,
        'partido_type' => Partido::class,
        'idEdicion' => $contexto['edicion']->id,
        'idEquipo' => $local->id,
        'goles_reportados' => 2,
    ]);

    EquipoEdicion::where('idEquipo', $local->id)->update(['golesContra' => 1]);

    Partido::deleting(function (): void {
        throw new RuntimeException('Fallo simulado al eliminar el partido');
    });

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->delete(route('partido.destroy', ['partido' => $partido->id, 'idEdicion' => $contexto['edicion']->id]));

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));
    $response->assertSessionHas('error', 'No se pudo eliminar el partido. Intenta nuevamente.');
    Log::assertLogged('error');

    $this->assertDatabaseHas('partidos', ['id' => $partido->id, 'golesEquipoLocal' => 2]);
    $this->assertDatabaseHas('planilla_jugadores', ['id' => $planilla->id]);
    $this->assertDatabaseHas('goles_arbitros_reportados', ['partido_id' => $partido->id]);
    $this->assertDatabaseHas('tabla_posiciones', ['idEquipo' => $local->id, 'jugado' => 1, 'puntos' => 3, 'golesFavor' => 2]);
    $this->assertDatabaseHas('jugadores', ['dni' => '30111222', 'goles_totales' => 2]);
    $this->assertDatabaseHas('tabla_goleadores', ['dni_jugador' => '30111222', 'cantidadGoles' => 2]);
    $this->assertDatabaseHas('equipo_ediciones', ['idEquipo' => $local->id, 'golesContra' => 1]);
});

it('al eliminar un partido interzonal recalcula los golesContra con el grupo propio de cada equipo', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoInterzonal();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $contexto['grupoA']->equipos()->attach($local->id);
    $contexto['grupoB']->equipos()->attach($visitante->id);

    $partido = crearPartidoRegular($contexto, $local, $visitante, [
        'es_interzonal' => 1,
        'idGrupo' => $contexto['grupoA']->id,
    ]);
    registrarResultadoPartido($partido, 3, 1);

    // Cada equipo arrastra en su grupo el resto de su fase regular ademas del interzonal.
    TablaPosicion::create([
        'idGrupo' => $contexto['grupoA']->id,
        'idEquipo' => $local->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesFavor' => 4,
        'golesContra' => 3,
        'diferenciaGoles' => 1,
        'jugado' => 2,
        'ganado' => 1,
        'perdido' => 1,
        'empatado' => 0,
        'puntos' => 3,
    ]);

    TablaPosicion::create([
        'idGrupo' => $contexto['grupoB']->id,
        'idEquipo' => $visitante->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesFavor' => 2,
        'golesContra' => 4,
        'diferenciaGoles' => -2,
        'jugado' => 2,
        'ganado' => 0,
        'perdido' => 2,
        'empatado' => 0,
        'puntos' => 0,
    ]);

    EquipoEdicion::where('idEquipo', $local->id)->update(['golesContra' => 7]);
    EquipoEdicion::where('idEquipo', $visitante->id)->update(['golesContra' => 7]);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->delete(route('partido.destroy', array_filter([
            'partido' => $partido->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupoA']->id,
            'idFecha' => $contexto['fecha']->id,
        ])));

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupoA']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));
    $response->assertSessionHas('status', 'Partido eliminado con éxito.');

    $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);

    // La revision quita solo el interzonal: 3-1 para el local y 1-3 para el visitante.
    $this->assertDatabaseHas('tabla_posiciones', ['idEquipo' => $local->id, 'idGrupo' => $contexto['grupoA']->id, 'jugado' => 1, 'golesFavor' => 1, 'golesContra' => 2]);
    $this->assertDatabaseHas('tabla_posiciones', ['idEquipo' => $visitante->id, 'idGrupo' => $contexto['grupoB']->id, 'jugado' => 1, 'golesFavor' => 1, 'golesContra' => 1]);

    // Cada equipo_edicion se recalcula con las tablas de su propio grupo.
    $this->assertDatabaseHas('equipo_ediciones', ['idEquipo' => $local->id, 'golesContra' => 2]);
    $this->assertDatabaseHas('equipo_ediciones', ['idEquipo' => $visitante->id, 'golesContra' => 1]);
});

it('al eliminar un partido sin resultado solo borra el partido y conserva filtros', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $partido = crearPartidoRegular($contexto, $local, $visitante);

    $response = $this->actingAs($admin)
        ->withSession(['_token' => tokenDePrueba()])
        ->withHeaders(cabecerasCsrf())
        ->delete(route('partido.destroy', array_filter([
            'partido' => $partido->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
            'idGrupo' => $contexto['grupo']->id,
            'idFecha' => $contexto['fecha']->id,
            'horario' => '10:00',
        ])));

    $response->assertRedirect(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '10:00',
    ]));
    $response->assertSessionHas('status', 'Partido eliminado con éxito.');

    $this->assertDatabaseMissing('partidos', ['id' => $partido->id]);
    $this->assertDatabaseCount('tabla_posiciones', 0);
});

it('la vista index muestra el formulario de eliminacion con confirmacion y filtros', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $partido = crearPartidoRegular($contexto, $local, $visitante);

    $urlEliminacion = route('partido.destroy', array_filter([
        'partido' => $partido->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));

    $response = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
    ]));

    $response->assertOk();
    $response->assertSee(htmlspecialchars($urlEliminacion, ENT_QUOTES), false);
    $response->assertSee('name="_method" value="DELETE"', false);
    // T5 (panel-admin-redesign): el borrado directo con onsubmit="return confirm(...)"
    // pasa a x-ui.confirm-delete (data-confirm-delete + SweetAlert/window.confirm
    // via resources/js/admin/confirm-delete.js), por eso ya no hay un literal
    // "confirm(" en el HTML servido.
    $response->assertSee('data-confirm-delete', false);
    $response->assertSee('event.defaultPrevented', false);
    $response->assertSee("loader.classList.remove('hidden')", false);
    $response->assertSee('md:grid-cols-4', false);
    $response->assertSee('md:max-w-2xl', false);

    $contenido = $response->getContent();

    // T5: los 4 controles de la tarjeta (Cargar/Editar resultado, Planillas,
    // Editar y el Eliminar de x-ui.confirm-delete) comparten ahora la firma
    // compacta de x-ui.button tamaño "sm", en vez del viejo pill "h-9 w-full
    // rounded-full" hand-typed.
    $firmaBotonSm = 'inline-flex items-center justify-center gap-2 font-semibold transition focus:outline-none focus-visible:ring-2 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg px-3 py-1.5 text-xs';
    expect(substr_count($contenido, $firmaBotonSm))->toBe(4);
    $documento = new DOMDocument;
    @$documento->loadHTML($contenido);
    $xpath = new DOMXPath($documento);
    $accionesLegacy = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " md:grid-cols-4 ")]')->item(0);
    expect($accionesLegacy)->not->toBeNull();
    expect($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " min-h-11 ")]', $accionesLegacy)->length)->toBe(0);
    $enlaceCobros = $xpath->query('//a[normalize-space(.)="Cobros de este partido"]');
    expect($enlaceCobros->length)->toBe(1);
    expect(explode(' ', $enlaceCobros->item(0)->getAttribute('class')))->toContain('min-h-11');
});

it('la vista edit deshabilita los equipos solo cuando el partido tiene actividad', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    $partidoJugado = crearPartidoRegular($contexto, $local, $visitante, ['jugado' => 1]);
    registrarResultadoPartido($partidoJugado, 2, 1);

    $respuestaJugado = $this->actingAs($admin)->get(route('partido.edit', [
        'partido' => $partidoJugado->id,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuestaJugado->assertOk();
    $respuestaJugado->assertSee('Los equipos no pueden modificarse', false);

    $partidoNuevo = crearPartidoRegular($contexto, $local, $visitante);
    $respuestaNuevo = $this->actingAs($admin)->get(route('partido.edit', [
        'partido' => $partidoNuevo->id,
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuestaNuevo->assertOk();
    $respuestaNuevo->assertDontSee('Los equipos no pueden modificarse', false);
});

it('muestra una unica accion primaria para crear partidos que preserva los filtros activos', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuesta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '10:00',
        'busqueda' => 'Rio',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    // T5 (panel-admin-redesign): sin partidos cargados, el estado vacío
    // agrega una segunda acción "Nuevo partido" (la del encabezado se
    // mantiene única; la del estado vacío es la acción del criterio de
    // aceptación "estado vacío con dirección").
    expect(substr_count($contenido, 'Nuevo partido'))->toBe(2);

    $hrefCreacion = hrefQueContenga($contenido, '/partido/create');

    expect($hrefCreacion)->not->toBeNull();

    parse_str(parse_url((string) $hrefCreacion, PHP_URL_QUERY) ?? '', $queryCreacion);

    expect($queryCreacion)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'idCategoria' => (string) $contexto['categoria']->id,
        'idGrupo' => (string) $contexto['grupo']->id,
        'idFecha' => (string) $contexto['fecha']->id,
        'horario' => '10:00',
        'busqueda' => 'Rio',
    ]);
});

it('recurre a la seleccion de categoria para la accion primaria sin contexto de categoria y grupo', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuesta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    // T5: idem, el estado vacío (sin partidos en este contexto) suma su
    // propia acción "Nuevo partido" a la del encabezado.
    expect(substr_count($contenido, 'Nuevo partido'))->toBe(2);
    expect(hrefQueContenga($contenido, '/partido/create'))->toBeNull();

    $urlSeleccion = route('seleccionar-categoria', [
        'idEdicion' => $contexto['edicion']->id,
        'tipo' => 'partido',
        'forzarSeleccion' => 1,
    ]);

    expect(hrefsDelContenido($contenido))->toContain($urlSeleccion);
});

it('mantiene la creacion interzonal como accion secundaria disponible solo con contexto de categoria', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuestaConCategoria = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $respuestaConCategoria->assertOk();
    $contenidoConCategoria = $respuestaConCategoria->getContent();

    // T5: sin partidos en este contexto, el estado vacío suma su propia
    // acción "Nuevo partido" a la del encabezado.
    expect(substr_count($contenidoConCategoria, 'Nuevo partido'))->toBe(2);

    $hrefsInterzonal = collect(hrefsDelContenido($contenidoConCategoria))
        ->filter(fn (string $href): bool => str_contains($href, '/partido-interzonal/create'))
        ->values();

    expect($hrefsInterzonal)->toHaveCount(1);

    parse_str(parse_url((string) $hrefsInterzonal[0], PHP_URL_QUERY) ?? '', $queryInterzonal);

    expect($queryInterzonal)->toMatchArray([
        'idEdicion' => (string) $contexto['edicion']->id,
        'idCategoria' => (string) $contexto['categoria']->id,
    ]);

    $respuestaSinCategoria = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
    ]));

    $respuestaSinCategoria->assertOk();

    $hrefsSinCategoria = collect(hrefsDelContenido($respuestaSinCategoria->getContent()))
        ->filter(fn (string $href): bool => str_contains($href, '/partido-interzonal/create'));

    expect($hrefsSinCategoria)->toBeEmpty();
});

it('la barra de acciones muestra un unico signo mas y controles compactos equivalentes', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuesta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    // El icono SVG ya comunica el alta: el texto visible no debe repetir el signo +.
    expect($contenido)->not->toContain('+ Nuevo partido');
    // T5 (panel-admin-redesign): sin partidos en este contexto, el estado
    // vacío suma su propia acción "Nuevo partido" a la del encabezado.
    expect(substr_count($contenido, 'Nuevo partido'))->toBe(2);

    // T5: los tres controles del encabezado (Nuevo partido, Partido
    // interzonal, Imprimir planilla de árbitros) comparten la misma firma
    // compacta de x-ui.button tamaño "md" (base + forma + tamaño, sin el
    // color de variante), en vez del viejo pill "h-9 w-full rounded-full".
    // "Limpiar todo" pasa a ser un link de texto: limpiar filtros no es una
    // acción destructiva y no lleva el botón rojo.
    $firmaCompactaCompartida = 'inline-flex items-center justify-center gap-2 font-semibold transition focus:outline-none focus-visible:ring-2 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg px-4 py-2 text-sm';
    expect(substr_count($contenido, $firmaCompactaCompartida))->toBe(3);

    foreach (['Nuevo partido', 'Partido interzonal', 'Imprimir planilla de árbitros'] as $accion) {
        $bloque = bloqueDeAccion($contenido, $accion);

        expect($bloque)->not->toBeNull();
        expect($bloque)->toContain('aria-hidden="true"');
    }

    // Interzonal: variante secundaria de x-ui.button (brand-teal), distinta
    // de la primaria (brand-navy) de "Nuevo partido".
    $bloqueInterzonal = bloqueDeAccion($contenido, 'Partido interzonal');

    expect($bloqueInterzonal)->toContain('bg-brand-teal');
    expect($bloqueInterzonal)->not->toContain('bg-brand-navy');
});

it('muestra la cantidad de partidos que devuelve el filtro aplicado', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    crearPartidoRegular($contexto, $local, $visitante, ['horario_datetime' => '2026-04-05 10:00:00']);
    crearPartidoRegular($contexto, $visitante, $local, ['horario_datetime' => '2026-04-05 11:00:00']);

    $respuestaFiltrada = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'horario' => '10:00',
    ]));

    $respuestaFiltrada->assertOk();
    $contenidoFiltrado = $respuestaFiltrada->getContent();

    expect(preg_match('/id="contador-partidos"[^>]*>\s*Mostrando 1 partido\s*</', $contenidoFiltrado))->toBe(1);
    expect(preg_match('/id="contador-partidos"[^>]*>\s*Mostrando 2 partidos\s*</', $contenidoFiltrado))->toBe(0);

    $respuestaCompleta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
    ]));

    $respuestaCompleta->assertOk();
    expect(preg_match('/id="contador-partidos"[^>]*>\s*Mostrando 2 partidos\s*</', $respuestaCompleta->getContent()))->toBe(1);
});

it('repopula el buscador del listado con la busqueda activa', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    crearPartidoRegular($contexto, $local, $visitante);

    $respuesta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'busqueda' => 'Rio Grande',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    expect(preg_match('/id="buscar-equipo"[^>]*value="Rio Grande"/', $contenido))->toBe(1);
    expect(preg_match('/id="contador-partidos"[^>]*>\s*Mostrando 1 partido\s*</', $contenido))->toBe(1);
});

it('declara la sincronizacion de busqueda en la url sin recargar el listado', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $local = crearEquipoPartido($contexto, 'Rio Grande');
    $visitante = crearEquipoPartido($contexto, 'Yerba Buena');
    crearPartidoRegular($contexto, $local, $visitante);

    $respuesta = $this->actingAs($admin)->get(route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'busqueda' => 'Rio',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    // Contrato estatico del script servido: la busqueda se actualiza con
    // replaceState sin navegacion y eliminar filtros la limpia de la URL.
    // El comportamiento DOM en vivo se verifica con Playwright (T3).
    expect($contenido)->toContain('history.replaceState');
    expect($contenido)->toContain("busqueda: ''");
});

it('prefija la fecha y el horario del contexto al crear un partido regular', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();
    $fecha2 = Fecha::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'nombre' => 'Fecha 2']);

    $respuesta = $this->actingAs($admin)->get(route('partido.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '16:30',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    expect(preg_match('/<option value="'.$contexto['fecha']->id.'"[^>]*selected/', $contenido))->toBe(1);
    expect(preg_match('/<option value="'.$fecha2->id.'"[^>]*selected/', $contenido))->toBe(0);

    expect(preg_match('/name="horario_datetime"[^>]*value="([^"]+)"/', $contenido, $coincidencias))->toBe(1);
    expect($coincidencias[1] ?? '')->toMatch('/T16:30(:00)?$/');
});

it('prefija la fecha y el horario del contexto al crear un partido interzonal', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoInterzonal();
    $fecha2 = Fecha::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'nombre' => 'Fecha 2']);

    $respuesta = $this->actingAs($admin)->get(route('partido.create-interzonal', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '16:30',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    expect(preg_match('/<option value="'.$contexto['fecha']->id.'"[^>]*selected/', $contenido))->toBe(1);
    expect(preg_match('/<option value="'.$fecha2->id.'"[^>]*selected/', $contenido))->toBe(0);

    expect(preg_match('/name="horario_datetime"[^>]*value="([^"]+)"/', $contenido, $coincidencias))->toBe(1);
    expect($coincidencias[1] ?? '')->toMatch('/T16:30(:00)?$/');
});

it('usa el proximo domingo como respaldo cuando el horario del contexto es invalido', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuesta = $this->actingAs($admin)->get(route('partido.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '25:99',
    ]));

    $respuesta->assertOk();

    expect(preg_match('/name="horario_datetime"[^>]*value="([^"]+)"/', $respuesta->getContent(), $coincidencias))->toBe(1);
    expect($coincidencias[1] ?? '')->toMatch('/T00:00$/');
});

it('usa el proximo domingo como respaldo en el interzonal cuando el horario del contexto es invalido', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoInterzonal();

    $respuesta = $this->actingAs($admin)->get(route('partido.create-interzonal', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => 'sin-horario',
    ]));

    $respuesta->assertOk();

    expect(preg_match('/name="horario_datetime"[^>]*value="([^"]+)"/', $respuesta->getContent(), $coincidencias))->toBe(1);
    expect($coincidencias[1] ?? '')->toMatch('/T00:00$/');
});

it('conserva los parametros de retorno al crear un partido regular', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $respuesta = $this->actingAs($admin)->get(route('partido.create', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '16:30',
        'busqueda' => 'Rio',
    ]));

    $respuesta->assertOk();
    $contenido = $respuesta->getContent();

    expect($contenido)->toContain('name="retorno_idCategoria" value="'.$contexto['categoria']->id.'"');
    expect($contenido)->toContain('name="retorno_idGrupo" value="'.$contexto['grupo']->id.'"');
    expect($contenido)->toContain('name="retorno_idFecha" value="'.$contexto['fecha']->id.'"');
    expect($contenido)->toContain('name="retorno_horario" value="16:30"');
    expect($contenido)->toContain('name="retorno_busqueda" value="Rio"');

    $urlRegreso = route('partido.index', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idGrupo' => $contexto['grupo']->id,
        'idFecha' => $contexto['fecha']->id,
        'horario' => '16:30',
        'busqueda' => 'Rio',
    ]);

    expect(hrefsDelContenido($contenido))->toContain($urlRegreso);
});

it('muestra el titulo de la seleccion de categoria legible sobre el fondo claro del panel', function () {
    $admin = crearAdminPartidos();
    $contexto = crearContextoPartidos();

    $contenido = $this->actingAs($admin)->get(route('seleccionar-categoria', [
        'idEdicion' => $contexto['edicion']->id,
        'tipo' => 'partido',
        'forzarSeleccion' => 1,
    ]))->assertOk()->getContent();

    expect($contenido)->toContain('<h1 class="text-2xl font-bold text-gray-800">Seleccionar contexto</h1>')
        ->not->toContain('<h1 class="text-xl font-bold text-white">')
        ->not->toContain('rounded-lg bg-white/10 p-3');
});
