<?php

// Tests del servicio de sugerencias de Premios individuales (T8c,
// panel-admin-redesign): orden de la sugerencia de goleador (goles desc,
// nombre asc) y de valla (golesContra asc, nombre asc), el tope de
// candidatos, que lo guardado suprima la sugerencia, que los duplicados
// guardados se listen todos y que las categorías sin nada se omitan.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Goleador;
use App\Models\Jugador;
use App\Models\TablaGoleador;
use App\Models\VallaMenosVencida;
use App\Services\Panel\SugerenciaPremios;

function crearServicioSugerenciaPremios(): SugerenciaPremios
{
    return new SugerenciaPremios;
}

/**
 * @return array{edicion: Edicion, categoria: Categoria}
 */
function crearContextoSugerenciaPremios(array $atributos = []): array
{
    $edicion = Edicion::create(['nombre' => $atributos['nombreEdicion'] ?? 'Edicion Sugerencia Premios']);
    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => $atributos['nombreCategoria'] ?? 'Sub 15 Premios',
    ]);

    return compact('edicion', 'categoria');
}

function crearEquipoParaSugerenciaPremios(Edicion $edicion, Categoria $categoria, string $nombre, int $golesContra = 0): Equipo
{
    $equipo = Equipo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => $nombre,
        'foto' => 'escudo.png',
    ]);

    EquipoEdicion::create([
        'idEdicion' => $edicion->id,
        'idEquipo' => $equipo->id,
        'idCategoria' => $categoria->id,
        'golesContra' => $golesContra,
    ]);

    return $equipo;
}

function crearFilaTablaGoleadorPremios(array $contexto, Equipo $equipo, string $dni, string $nombre, string $apellido, int $goles): TablaGoleador
{
    Jugador::create([
        'dni' => $dni,
        'nombre' => $nombre,
        'apellido' => $apellido,
        'goles_totales' => $goles,
        'partidos_totales' => 1,
    ]);

    return TablaGoleador::create([
        'idEquipo' => $equipo->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'dni_jugador' => $dni,
        'cantidadGoles' => $goles,
    ]);
}

// ---- Goleador -------------------------------------------------------------

it('ordena la sugerencia de goleador por goles desc y a igual goles por nombre asc', function () {
    $contexto = crearContextoSugerenciaPremios();
    $equipoA = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo A');
    $equipoB = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo B');
    $equipoC = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo C');

    // Insertados fuera de orden a propósito. Empate en goles entre Bravo
    // Bruno y Cabrera Carla: por nombre completo (apellido + nombre),
    // "Bravo Bruno" va antes que "Cabrera Carla".
    crearFilaTablaGoleadorPremios($contexto, $equipoB, '40100002', 'Bruno', 'Bravo', 5);
    crearFilaTablaGoleadorPremios($contexto, $equipoA, '40100001', 'Ana', 'Aguirre', 8);
    crearFilaTablaGoleadorPremios($contexto, $equipoC, '40100003', 'Carla', 'Cabrera', 5);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $categoria = $premios['categorias']->first();
    $sugerencias = $categoria['goleador']['sugerencias'];

    expect($sugerencias->pluck('dni_jugador')->all())->toBe(['40100001', '40100002', '40100003'])
        ->and($sugerencias->first()['esSugerida'])->toBeTrue()
        ->and($sugerencias->first()['label'])->toBe('Sugerido: Aguirre Ana (8 goles)')
        ->and($sugerencias->get(1)['esSugerida'])->toBeFalse();
});

it('capea la sugerencia de goleador en 10 candidatos', function () {
    $contexto = crearContextoSugerenciaPremios();

    for ($i = 1; $i <= 12; $i++) {
        $equipo = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], "Equipo Tope {$i}");
        crearFilaTablaGoleadorPremios($contexto, $equipo, sprintf('4020%04d', $i), "Jugador{$i}", 'Tope', 13 - $i);
    }

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $sugerencias = $premios['categorias']->first()['goleador']['sugerencias'];

    expect($sugerencias)->toHaveCount(10)
        ->and($sugerencias->first()['goles'])->toBe(12);
});

it('si ya hay un goleador guardado no vuelve a sugerir', function () {
    $contexto = crearContextoSugerenciaPremios();
    $equipo = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Guardado');
    crearFilaTablaGoleadorPremios($contexto, $equipo, '40300001', 'Mateo', 'Duarte', 5);

    Goleador::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Duarte Mateo',
    ]);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $goleador = $premios['categorias']->first()['goleador'];

    expect($goleador['sugerencias'])->toHaveCount(0)
        ->and($goleador['guardados'])->toHaveCount(1);
});

it('lista todos los goleadores duplicados guardados de una categoria', function () {
    $contexto = crearContextoSugerenciaPremios();
    $equipoA = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Dup A');
    $equipoB = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Dup B');

    Goleador::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idEquipo' => $equipoA->id, 'nombre' => 'Goleador Dup A']);
    Goleador::create(['idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id, 'idEquipo' => $equipoB->id, 'nombre' => 'Goleador Dup B']);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $goleador = $premios['categorias']->first()['goleador'];

    expect($goleador['guardados'])->toHaveCount(2)
        ->and($goleador['guardados']->pluck('nombre')->all())->toContain('Goleador Dup A', 'Goleador Dup B');
});

// ---- Valla ------------------------------------------------------------------

it('ordena la sugerencia de valla por goles en contra asc y a igual cantidad por nombre de equipo asc', function () {
    $contexto = crearContextoSugerenciaPremios();
    // Insertados fuera de orden a propósito.
    crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Medio', 5);
    crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Mejor', 2);
    crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Empate B', 5);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $sugerencias = $premios['categorias']->first()['valla']['sugerencias'];

    expect($sugerencias->pluck('nombreEquipo')->all())->toBe(['Equipo Mejor', 'Equipo Empate B', 'Equipo Medio'])
        ->and($sugerencias->first()['esSugerida'])->toBeTrue()
        ->and($sugerencias->first()['label'])->toBe('Sugerido: Equipo Mejor (2 en contra)');
});

it('si ya hay una valla guardada no vuelve a sugerir', function () {
    $contexto = crearContextoSugerenciaPremios();
    $equipo = crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Valla Guardada', 1);

    VallaMenosVencida::create([
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'idEquipo' => $equipo->id,
        'nombre' => 'Arquero Guardado',
    ]);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);
    $valla = $premios['categorias']->first()['valla'];

    expect($valla['sugerencias'])->toHaveCount(0)
        ->and($valla['guardados'])->toHaveCount(1);
});

// ---- Categorías vacías --------------------------------------------------

it('omite categorias sin nada guardado ni sugerido y marca tieneContenido en false', function () {
    $edicion = Edicion::create(['nombre' => 'Edicion Vacia Premios']);
    Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Categoria Vacia Premios']);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($edicion->id);

    expect($premios['categorias'])->toHaveCount(0)
        ->and($premios['tieneContenido'])->toBeFalse();
});

it('incluye la categoria cuando solo hay sugerencia de valla, aunque no haya de goleador', function () {
    $contexto = crearContextoSugerenciaPremios();
    crearEquipoParaSugerenciaPremios($contexto['edicion'], $contexto['categoria'], 'Equipo Solo Valla', 3);

    $premios = crearServicioSugerenciaPremios()->obtenerPremios($contexto['edicion']->id);

    expect($premios['categorias'])->toHaveCount(1)
        ->and($premios['tieneContenido'])->toBeTrue()
        ->and($premios['categorias']->first()['goleador']['sugerencias'])->toHaveCount(0)
        ->and($premios['categorias']->first()['valla']['sugerencias'])->toHaveCount(1);
});
