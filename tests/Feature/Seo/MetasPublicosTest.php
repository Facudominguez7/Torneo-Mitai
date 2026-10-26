<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;

// Helpers con prefijo propio: las funciones de Pest son globales entre archivos
// del mismo run y no pueden redeclararse (convencion de FixtureExploradorVistaTest).
function metasContexto(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Metas']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Fecha Metas',
    ]);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function metasPartido(array $contexto): void
{
    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Halcones',
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => 'Ceibos',
        'foto' => 'visita.png',
    ]);

    foreach ([$equipoLocal, $equipoVisitante] as $equipo) {
        EquipoEdicion::create([
            'idEdicion' => $contexto['edicion']->id,
            'idEquipo' => $equipo->id,
            'idCategoria' => $contexto['categoria']->id,
        ]);
    }
}

it('la home declara canonical, titulo y descripcion propios', function () {
    metasContexto();

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">')
        ->and($html)->toContain('<title>Torneo Mitai | Fútbol Infantil</title>')
        ->and($html)->toMatch('/<meta name="description" content="[^"]+">/');
});

it('campeones declara titulo y canonical de la pagina', function () {
    $contexto = metasContexto();

    $html = $this->get(route('campeones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<title>Campeones | Torneo Mitai</title>')
        ->and($html)->toContain('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/campeones">');
});

it('fixture declara titulo propio', function () {
    $contexto = metasContexto();
    metasPartido($contexto);

    $html = $this->get(route('fixture', [
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
    ]))->assertOk()->getContent();

    expect($html)->toContain('<title>Fixture | Torneo Mitai</title>');
});

it('las paginas de autenticacion llevan noindex', function () {
    expect($this->get('/login')->getContent())->toContain('noindex')
        ->and($this->get('/forgot-password')->getContent())->toContain('noindex');
});

it('la pagina de baja general de email lleva noindex', function () {
    $html = $this->get(route('email.baja-general.form'))->getContent();

    expect($html)->toContain('noindex');
});
