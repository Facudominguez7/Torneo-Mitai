<?php

// Tests unitarios del servicio de sugerencias del Podio (T8b,
// panel-admin-redesign): matching de "la final" por nombre de fase, elección
// de ida/vuelta por horario, determinación del ganador (global, goles,
// penales), supresión de sugerencias cuando ya hay algo cargado y el estado
// por categoría.

use App\Models\Campeon;
use App\Models\Categoria;
use App\Models\Copa;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\InstanciaFinal;
use App\Models\Subcampeon;
use App\Services\Panel\SugerenciaPodio;

function crearServicioSugerenciaPodio(): SugerenciaPodio
{
    return new SugerenciaPodio;
}

function crearContextoSugerenciaPodio(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Sugerencia Podio']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 15 Podio',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoParaSugerenciaPodio(Edicion $edicion, Categoria $categoria, string $nombre): Equipo
{
    return Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);
}

/**
 * @param  array<string, mixed>  $atributos
 */
function crearFinalParaSugerenciaPodio(array $contexto, Copa $copa, Fase $fase, Equipo $local, Equipo $visitante, array $atributos = []): InstanciaFinal
{
    $final = InstanciaFinal::create(array_merge([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCopa' => $copa->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'horario' => '2026-05-01 10:00:00',
        'cancha' => 1,
    ], array_diff_key($atributos, ['resultadoGlobal' => null])));

    if (array_key_exists('resultadoGlobal', $atributos)) {
        $final->forceFill(['resultadoGlobal' => $atributos['resultadoGlobal']])->save();
    }

    return $final;
}

it('reconoce "Final" y variantes como la final, pero no Semifinal ni Cuartos de final', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Fase Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Fase');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Fase');

    // Semifinal y Cuartos de final ya están jugados y no deberían aportar sugerencia.
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Semifinal']), $local, $visitante, [
        'golesEquipoLocal' => 5, 'golesEquipoVisitante' => 0, 'horario' => '2026-04-20 10:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Cuartos de final']), $local, $visitante, [
        'golesEquipoLocal' => 5, 'golesEquipoVisitante' => 0, 'horario' => '2026-04-10 10:00:00',
    ]);
    // "final " (con espacio y minúscula) sí cuenta como la final.
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'final ']), $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1, 'horario' => '2026-05-01 10:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->not->toBeNull()
        ->and($copaPodio['sugerencia']['nombreCampeon'])->toBe('Local Fase');
});

it('con ida y vuelta el agregado de goles coincide con el resultado de la vuelta y confirma el mismo campeon', function () {
    // Antes esta prueba decía "usa la de horario mas reciente", pero con
    // estos datos el agregado (0+4 vs 3+0) y la vuelta sola dan el mismo
    // ganador: no alcanza para distinguir "solo la última" de "agregado".
    // La prueba de agregado real (donde difieren) es la de más abajo.
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Ida Vuelta Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Ida Vuelta');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Ida Vuelta');

    // Ida: gana el visitante. Vuelta (más tarde): gana el local, y también el agregado (4-3).
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Ida']), $local, $visitante, [
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 3, 'horario' => '2026-05-01 10:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Vuelta']), $local, $visitante, [
        'golesEquipoLocal' => 4, 'golesEquipoVisitante' => 0, 'horario' => '2026-05-08 10:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Local Ida Vuelta');
});

it('con ida y vuelta sin resultadoGlobal parseable decide por el agregado de goles y no por la ultima ida sola', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Agregado Podio']);
    $equipoA = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo A Agregado');
    $equipoB = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo B Agregado');

    // Ida: A local gana 3-0. Vuelta (localía invertida): B local gana 1-0.
    // Por la última ida sola ganaría B, pero en el agregado (3-1) gana A.
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Ida']), $equipoA, $equipoB, [
        'golesEquipoLocal' => 3, 'golesEquipoVisitante' => 0, 'horario' => '2026-05-01 10:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Vuelta']), $equipoB, $equipoA, [
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 0, 'horario' => '2026-05-08 10:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Equipo A Agregado');
});

it('con empate en el agregado de goles decide por los penales de la ultima ida', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Agregado Penales Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Agregado Penales');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Agregado Penales');

    // Ida: local gana 2-1. Vuelta: visitante gana 1-0. Agregado 2-2: define
    // la ultima ida por penales.
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Ida']), $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 1, 'horario' => '2026-05-01 10:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Vuelta']), $local, $visitante, [
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 1, 'horario' => '2026-05-08 10:00:00',
        'penalesEquipoLocal' => 3, 'penalesEquipoVisitante' => 5,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Visitante Agregado Penales');
});

it('con ida y vuelta si una ida no se jugo no sugiere nada', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Ida Sin Jugar Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Ida Sin Jugar');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Ida Sin Jugar');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Ida']), $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0, 'horario' => '2026-05-01 10:00:00',
    ]);
    // Vuelta sin jugar (sin goles cargados todavía).
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Vuelta']), $visitante, $local, [
        'horario' => '2026-05-08 10:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->toBeNull();
});

it('con ida y vuelta acepta la localia invertida entre partidos', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Localia Invertida Podio']);
    $equipoA = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo A Invertida');
    $equipoB = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo B Invertida');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Ida']), $equipoA, $equipoB, [
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1, 'horario' => '2026-05-01 10:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final Vuelta']), $equipoB, $equipoA, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0, 'horario' => '2026-05-08 10:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Equipo B Invertida');
});

it('con varias finales entre equipos distintos no sugiere nada', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Finales Ambiguas Podio']);
    $equipoA = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo A Ambigua');
    $equipoB = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo B Ambigua');
    $equipoC = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo C Ambigua');
    $equipoD = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Equipo D Ambigua');
    $fase = Fase::create(['nombre' => 'Final']);

    // Caso real (edición 20): dos "Final" el mismo día con equipos distintos.
    crearFinalParaSugerenciaPodio($contexto, $copa, $fase, $equipoA, $equipoB, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0, 'horario' => '2026-07-12 17:00:00',
    ]);
    crearFinalParaSugerenciaPodio($contexto, $copa, $fase, $equipoC, $equipoD, [
        'golesEquipoLocal' => 0, 'golesEquipoVisitante' => 1, 'horario' => '2026-07-12 20:00:00',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->toBeNull()
        ->and(crearServicioSugerenciaPodio()->sugerenciaParaCopa($contexto['edicion']->id, $contexto['categoria']->id, $copa->id))->toBeNull();
});

it('decide el ganador por resultadoGlobal cuando esta cargado', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Global Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Global');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Global');

    // Los goles del partido dan ganador al local, pero el global dice lo contrario.
    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 3, 'golesEquipoVisitante' => 1, 'resultadoGlobal' => '2-4',
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Visitante Global');
});

it('decide el ganador por goles cuando no hay resultadoGlobal parseable', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Goles Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Goles');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Goles');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 2,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Visitante Goles');
});

it('con empate en goles decide por penales', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Penales Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Penales');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Penales');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 1, 'golesEquipoVisitante' => 1,
        'penalesEquipoLocal' => 3, 'penalesEquipoVisitante' => 4,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia']['nombreCampeon'])->toBe('Visitante Penales');
});

it('sin penales y con empate no sugiere nada', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Empate Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Empate');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Empate');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 2,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->toBeNull()
        ->and($copaPodio['definido'])->toBeFalse();
});

it('una final sin jugar no sugiere nada', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Sin Jugar Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Sin Jugar');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Sin Jugar');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->toBeNull();
});

it('si ya hay campeon o subcampeon guardado no vuelve a sugerir', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Ya Cargada Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Ya Cargada');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Ya Cargada');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 2, 'golesEquipoVisitante' => 0,
    ]);

    Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $local->id,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio['sugerencia'])->toBeNull()
        ->and($copaPodio['campeones'])->toHaveCount(1)
        ->and($copaPodio['definido'])->toBeFalse();
});

it('lista una copa con campeon guardado aunque no tenga instancias', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Sin Instancias Podio']);
    $equipo = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Sin Instancias');

    Campeon::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idCopa' => $copa->id,
        'idEquipo' => $equipo->id,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);
    $copaPodio = $podio['categorias']->first()['copas']->firstWhere('idCopa', $copa->id);

    expect($copaPodio)->not->toBeNull()
        ->and($copaPodio['sugerencia'])->toBeNull()
        ->and($copaPodio['definido'])->toBeFalse();
});

it('calcula el estado Definido cuando todas las copas tienen campeon y subcampeon', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Definida Podio']);
    $campeon = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Campeon Definido');
    $subcampeon = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Subcampeon Definido');

    Campeon::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idCopa' => $copa->id, 'idEquipo' => $campeon->id]);
    Subcampeon::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idCopa' => $copa->id, 'idEquipo' => $subcampeon->id]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);

    expect($podio['categorias']->first()['estado'])->toBe(['tipo' => 'definido', 'label' => 'Definido']);
});

it('calcula el estado Final jugada cuando hay una sugerencia pendiente', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Final Jugada Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Final Jugada');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Final Jugada');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 3, 'golesEquipoVisitante' => 1,
    ]);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);

    expect($podio['categorias']->first()['estado'])->toBe(['tipo' => 'final_jugada', 'label' => 'Final jugada']);
});

it('calcula el estado En juego cuando hay instancias pero la final no se jugo', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa En Juego Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local En Juego');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante En Juego');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($contexto['edicion']->id);

    expect($podio['categorias']->first()['estado'])->toBe(['tipo' => 'en_juego', 'label' => 'En juego']);
});

it('omite categorias sin instancias ni nada guardado y marca tieneContenido en false', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Vacia Podio']);
    Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Vacia Podio']);

    $podio = crearServicioSugerenciaPodio()->obtenerPodio($edicion->id);

    expect($podio['categorias'])->toHaveCount(0)
        ->and($podio['tieneContenido'])->toBeFalse();
});

it('sugerenciaParaCopa recalcula server-side e ignora la copa incorrecta', function () {
    $contexto = crearContextoSugerenciaPodio();
    $copa = Copa::create(['nombre' => 'Copa Recalculo Podio']);
    $otraCopa = Copa::create(['nombre' => 'Otra Copa Recalculo Podio']);
    $local = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Local Recalculo');
    $visitante = crearEquipoParaSugerenciaPodio($contexto['edicion'], $contexto['categoria'], 'Visitante Recalculo');

    crearFinalParaSugerenciaPodio($contexto, $copa, Fase::create(['nombre' => 'Final']), $local, $visitante, [
        'golesEquipoLocal' => 5, 'golesEquipoVisitante' => 0,
    ]);

    $servicio = crearServicioSugerenciaPodio();

    $sugerencia = $servicio->sugerenciaParaCopa($contexto['edicion']->id, $contexto['categoria']->id, $copa->id);
    $sinSugerencia = $servicio->sugerenciaParaCopa($contexto['edicion']->id, $contexto['categoria']->id, $otraCopa->id);

    expect($sugerencia['idEquipoCampeon'])->toBe($local->id)
        ->and($sinSugerencia)->toBeNull();
});
