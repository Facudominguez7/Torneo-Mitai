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
    $response->assertSee('confirm(', false);
    $response->assertSee('event.defaultPrevented', false);
    $response->assertSee("loader.classList.remove('hidden')", false);
    $response->assertSee('md:grid-cols-4', false);
    $response->assertSee('md:max-w-2xl', false);

    $contenido = $response->getContent();

    expect(substr_count($contenido, 'inline-flex h-9 w-full items-center justify-center rounded-full'))->toBe(4);
    expect(substr_count($contenido, 'text-center text-sm font-semibold text-white transition hover:bg-'))->toBe(4);
    expect($contenido)->not->toContain('min-h-11');
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
