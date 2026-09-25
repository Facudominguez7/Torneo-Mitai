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
    $response->assertSee('2 - 2');
    $response->assertSee('Finalizado');

    // Penales y resultado global, propios de instancias finales.
    $response->assertSee('Penales: 5 - 4');
    $response->assertSee('Global: 4 - 3');

    // Label "Resultado" (no "vs") en el bloque desktop, exclusivo de esta vista.
    $response->assertSee('Resultado');

    // Link de planilla publica con tipoPartido=instancia_final, no "partido"
    // (el atributo x-on:click JSON-encodea la URL, por eso se verifican sus
    // partes en vez del string exacto).
    $response->assertSee('planillas-publicas\/instancia_final', false);
    $response->assertSee($instancia->public_token, false);
});

it('no deja el markup de tarjeta duplicado a mano en instancias-finales.blade.php', function () {
    $contenido = file_get_contents(resource_path('views/layouts/instancias-finales.blade.php'));

    expect($contenido)->toContain('<x-tarjeta-partido')
        ->and($contenido)->not->toContain('partido-card rounded-3xl border border-brand-gold/30 bg-gradient-to-br from-brand-navy via-brand-teal to-brand-navy');
});
