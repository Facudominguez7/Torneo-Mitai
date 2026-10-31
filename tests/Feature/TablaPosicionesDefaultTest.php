<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Grupo;
use App\Models\TablaPosicion;

function crearContextoPosicionesDefault(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Posiciones Default']);

    $categoriaPrimera = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 17',
    ]);
    $categoriaSegunda = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 15',
    ]);

    $grupoPrimero = Grupo::create([
        'idCategoria' => $categoriaPrimera->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);
    $grupoSegundo = Grupo::create([
        'idCategoria' => $categoriaSegunda->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo B',
    ]);

    $grupoAdicionalPrimera = Grupo::create([
        'idCategoria' => $categoriaPrimera->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo C',
    ]);

    $equipoPrimero = Equipo::create([
        'idCategoria' => $categoriaPrimera->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo Norte',
        'foto' => 'escudo.png',
    ]);
    $equipoSegundo = Equipo::create([
        'idCategoria' => $categoriaSegunda->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Equipo Sur',
        'foto' => 'escudo.png',
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoPrimero->id,
        'idEquipo' => $equipoPrimero->id,
        'idEdicion' => $edicion->id,
        'puntos' => 3,
        'jugado' => 1,
        'diferenciaGoles' => 2,
        'golesFavor' => 3,
        'golesContra' => 1,
        'ganado' => 1,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    TablaPosicion::create([
        'idGrupo' => $grupoSegundo->id,
        'idEquipo' => $equipoSegundo->id,
        'idEdicion' => $edicion->id,
        'puntos' => 6,
        'jugado' => 2,
        'diferenciaGoles' => 1,
        'golesFavor' => 4,
        'golesContra' => 3,
        'ganado' => 2,
        'perdido' => 0,
        'empatado' => 0,
    ]);

    return [
        'edicion' => $edicion,
        'categoriaPrimera' => $categoriaPrimera,
        'categoriaSegunda' => $categoriaSegunda,
        'grupoPrimeraCategoria' => $grupoPrimero,
        'grupoAdicionalPrimera' => $grupoAdicionalPrimera,
    ];
}

it('resuelve la primera categoria y su primer grupo por defecto en la tabla de posiciones', function () {
    $contexto = crearContextoPosicionesDefault();

    $response = $this->get(route('tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('Sub 17')
        ->and($html)->toContain('Equipo Norte')
        ->and($html)->not->toContain('Categoría no encontrada');
});

it('respeta la categoria explicita de la segunda categoria sin caer al default', function () {
    $contexto = crearContextoPosicionesDefault();

    $response = $this->get(route('tabla-posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoriaSegunda']->id,
    ]));

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('Sub 15')
        ->and($html)->toContain('Equipo Sur')
        ->and($html)->not->toContain('Equipo Norte')
        ->and($html)->not->toContain('Categoría no encontrada');
});

it('presenta la tabla de cancha con escudos y estadísticas accesibles', function () {
    $contexto = crearContextoPosicionesDefault();

    $this->get(route('tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Posiciones del grupo seleccionado')
        ->assertSee('Ganados: 1, empatados: 0, perdidos: 0, goles a favor: 3, goles en contra: 1')
        ->assertSee('escudo.png')
        ->assertSee('Equipo Norte')
        ->assertDontSee('Resumen actualizado de puntos');
});

it('mantiene una base clara para los escudos de posiciones', function () {
    expect(file_get_contents(resource_path('css/app.css')))
        ->toMatch('/\.posiciones-equipo img\s*\{[^}]*background:\s*#fff\s*;/');
});

it('responde 200 en la tabla de posiciones sin idEdicion manteniendo el comportamiento legacy', function () {
    $response = $this->get(route('tabla-posiciones'));

    $response->assertOk();
});

it('muestra el switcher de categorias y grupos y ya no ofrece volver al fixture', function () {
    $contexto = crearContextoPosicionesDefault();

    $response = $this->get(route('tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk();
    // Blade escapa los & de la query string en los href; decodifico para
    // comparar contra las URLs reales generadas por route().
    $html = html_entity_decode($response->getContent(), ENT_QUOTES);
    $urlOtraCategoria = route('tabla-posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoriaSegunda']->id,
    ]);
    $urlGrupoDefault = route('tabla-posiciones', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoriaPrimera']->id,
        'idGrupo' => $contexto['grupoPrimeraCategoria']->id,
    ]);

    expect($html)->toContain($urlOtraCategoria)
        ->and($html)->toContain($urlGrupoDefault)
        ->and($html)->not->toContain('Volver al fixture');
});
