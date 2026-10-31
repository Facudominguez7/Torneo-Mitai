<?php

// T2.D: layouts/instancias-finales.blade.php duplicaba a mano la tarjeta de
// partido (~line 180) en vez de reusar x-tarjeta-partido. Este test prueba
// que, tras deduplicarla via props adicionales del componente, el resultado
// renderizado sigue mostrando el equipo, el marcador, "Finalizado" (a pesar
// de que instancias_finales no tiene columna `jugado`), penales, resultado
// global y el link de planilla publica con tipoPartido=instancia_final.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fase;
use App\Models\InstanciaFinal;

it('renderiza una instancia final finalizada con marcador, penales y resultado global via x-tarjeta-partido', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Instancias Compartidas']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $fase = Fase::create(['nombre' => 'Final']);
    $local = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Rojo',
        'foto' => 'rojo.png',
    ]);
    $visitante = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Mitai Negro',
        'foto' => 'negro.png',
    ]);

    $instancia = InstanciaFinal::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'golesEquipoLocal' => 2,
        'golesEquipoVisitante' => 2,
        'penalesEquipoLocal' => 5,
        'penalesEquipoVisitante' => 4,
        'horario' => now()->subDay(),
        'cancha' => '1',
    ]);
    $instancia->resultadoGlobal = '4 - 3';
    $instancia->save();

    $response = $this->get(route('instancias-finales', ['idEdicion' => $edicion->id]));

    $response->assertOk();

    // Equipos y marcador, sin necesidad de columna `jugado`.
    $response->assertSee('Mitai Rojo');
    $response->assertSee('Mitai Negro');
    $response->assertSeeInOrder(['Mitai Rojo', '<strong>2</strong>', 'Mitai Negro', '<strong>2</strong>'], false);
    $response->assertDontSee('Resultado: 2 - 2');
    $response->assertSee('Finalizado');

    // Penales y resultado global, propios de instancias finales.
    $response->assertSee('Penales: 5 - 4');
    $response->assertSee('Global: 4 - 3');
    $response->assertSee('Ganador del partido: Mitai Rojo');
    $response->assertSee('Sin copa');
    $response->assertSee('Camino a la copa');

    // Link de planilla publica con tipoPartido=instancia_final, no "partido"
    // (el atributo x-on:click JSON-encodea la URL, por eso se verifican sus
    // partes en vez del string exacto).
    $response->assertSee('planillas-publicas\/instancia_final', false);
    $response->assertSee($instancia->public_token, false);
});

it('muestra un estado vacío real y filtros utilizables sin javascript', function () {
    $edicion = Edicion::create(['nombre' => 'Edición sin finales']);

    $this->get(route('instancias-finales', ['idEdicion' => $edicion->id]))
        ->assertOk()
        ->assertSee('No hay partidos de finales para esta edición.')
        ->assertDontSee('Limpiar filtros')
        ->assertSee('Aplicar filtros')
        ->assertSee('Edición sin finales')
        ->assertSee('type="hidden" name="idEdicion"', false)
        ->assertDontSee('<select name="idEdicion"', false);
});

it('ofrece limpiar únicamente cuando el resultado vacío tiene filtros activos', function () {
    $edicion = Edicion::create(['nombre' => 'Edición filtrada']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);

    $response = $this->get(route('instancias-finales', ['idEdicion' => $edicion->id, 'categoria' => $categoria->id]))
        ->assertOk()
        ->assertSee('No hay partidos de finales para los filtros seleccionados.')
        ->assertSee('Limpiar filtros');

    expect(html_entity_decode($response->getContent(), ENT_QUOTES))
        ->toContain(route('instancias-finales', ['idEdicion' => $edicion->id]));
});

it('indica un ganador individual únicamente cuando el resultado lo determina', function (?int $local, ?int $visitante, ?int $penalLocal, ?int $penalVisitante, ?string $ganador) {
    $edicion = Edicion::create(['nombre' => 'Edición ganadores']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $fase = Fase::create(['nombre' => 'Final']);
    $equipoLocal = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Mitai Rojo', 'foto' => 'rojo.png']);
    $equipoVisitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Mitai Negro', 'foto' => 'negro.png']);
    InstanciaFinal::create([
        'idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'idFase' => $fase->id,
        'idEquipoLocal' => $equipoLocal->id, 'idEquipoVisitante' => $equipoVisitante->id,
        'golesEquipoLocal' => $local, 'golesEquipoVisitante' => $visitante,
        'penalesEquipoLocal' => $penalLocal, 'penalesEquipoVisitante' => $penalVisitante,
        'horario' => now()->subDay(), 'cancha' => '1',
    ]);
    $response = $this->get(route('instancias-finales', ['idEdicion' => $edicion->id]))->assertOk();
    if ($ganador === null) {
        $response->assertDontSee('Ganador del partido:');
    } else {
        $response->assertSee('Ganador del partido: '.$ganador);
    }
})->with([
    'goles local' => [2, 1, null, null, 'Mitai Rojo'],
    'goles visita' => [0, 1, null, null, 'Mitai Negro'],
    'penales visita' => [2, 2, 3, 4, 'Mitai Negro'],
    'empate' => [2, 2, null, null, null],
    'penales incompletos' => [2, 2, 3, null, null],
    'penales empatados' => [2, 2, 3, 3, null],
    'marcador ausente' => [null, null, null, null, null],
    'gol visitante ausente' => [1, null, 5, 4, null],
    'gol local ausente' => [null, 1, 5, 4, null],
]);

it('ordena las etapas de cada categoría con las finales al final', function () {
    $edicion = Edicion::create(['nombre' => 'Edición etapas']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 16']);
    $local = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Local', 'foto' => 'local.png']);
    $visitante = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Visitante', 'foto' => 'visita.png']);
    foreach (['Final', 'Gran Final', 'Semifinal', 'Tumbada - Ida'] as $nombre) {
        $fase = Fase::create(['nombre' => $nombre]);
        InstanciaFinal::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'idFase' => $fase->id, 'idEquipoLocal' => $local->id, 'idEquipoVisitante' => $visitante->id, 'horario' => now(), 'cancha' => '1']);
    }

    $this->get(route('instancias-finales', ['idEdicion' => $edicion->id]))
        ->assertOk()
        ->assertSeeInOrder(['Rondas anteriores', 'Tumbada - Ida', 'Semifinales', 'Semifinal', 'Finales', 'Final'])
        ->assertSeeInOrder(['data-etapa="final"', 'Gran Final'], false);
});

it('no deja el markup de tarjeta duplicado a mano en instancias-finales.blade.php', function () {
    $contenido = file_get_contents(resource_path('views/layouts/instancias-finales.blade.php'));

    expect($contenido)->toContain('<x-tarjeta-partido')
        ->and($contenido)->not->toContain('partido-card rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy via-brand-teal to-brand-navy');
});
