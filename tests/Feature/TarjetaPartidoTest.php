<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;

// Helpers con prefijo propio: las funciones de Pest son globales entre archivos
// del mismo run y no pueden redeclararse (convención de FixtureExploradorVistaTest).
function tarjetaBase(): array
{
    $edicion = Edicion::create(['nombre' => 'Edicion Tarjeta Partido']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 14']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);

    return compact('edicion', 'categoria', 'grupo');
}

function tarjetaFecha(array $contexto, string $nombre): Fecha
{
    return Fecha::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function tarjetaPartido(
    array $contexto,
    Fecha $fecha,
    string $horario,
    string $local = 'Leones del Norte',
    string $visitante = 'Tigres del Sur',
    int $jugado = 0,
    ?int $golesLocal = null,
    ?int $golesVisitante = null,
    ?int $idGrupo = 0,
): Partido {
    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $local,
        'foto' => 'local.png',
    ]);
    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $visitante,
        'foto' => 'visita.png',
    ]);

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $idGrupo === 0 ? $contexto['grupo']->id : $idGrupo,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? $golesLocal : null,
        'golesEquipoVisitante' => $jugado === 1 ? $golesVisitante : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

describe('fixture: tarjeta de partido (caracterización previa al refactor)', function () {
    it('renderiza las tarjetas estandar con equipos marcador y estado', function () {
        $contexto = tarjetaBase();
        $pasada = tarjetaFecha($contexto, 'Fecha 1');
        $futura = tarjetaFecha($contexto, 'Fecha 2');

        $jugado = tarjetaPartido(
            $contexto,
            $pasada,
            now()->subDays(3)->format('Y-m-d H:i:s'),
            jugado: 1,
            golesLocal: 2,
            golesVisitante: 1,
        );
        $pendiente = tarjetaPartido($contexto, $futura, now()->addDays(3)->format('Y-m-d H:i:s'));

        $html = $this->get('/fixture?idEdicion='.$contexto['edicion']->id.'&idFecha=todas')
            ->assertOk()
            ->getContent();

        expect($html)
            ->toContain('id="partido-'.$jugado->id.'"')
            ->toContain('id="partido-'.$pendiente->id.'"')
            ->toContain('data-jugado="1"')
            ->toContain('data-jugado="0"')
            ->toContain('data-local="leones del norte"')
            ->toContain('data-visitante="tigres del sur"')
            ->toContain('2 - 1')
            ->toContain('Finalizado')
            // Un pendiente con horario futuro renderiza pill "Próximo", no "Pendiente".
            ->toContain('Próximo');
    });

    it('renderiza la tarjeta interzonal con su badge y gradiente ambar', function () {
        $contexto = tarjetaBase();
        $futura = tarjetaFecha($contexto, 'Fecha 2');

        $interzonal = tarjetaPartido(
            $contexto,
            $futura,
            now()->addDays(3)->format('Y-m-d H:i:s'),
            local: 'Union del Este',
            visitante: 'Federal Misiones',
            idGrupo: null,
        );

        $html = $this->get('/fixture?idEdicion='.$contexto['edicion']->id.'&idFecha=todas')
            ->assertOk()
            ->getContent();

        expect($html)
            ->toContain('id="partido-'.$interzonal->id.'"')
            ->toContain('data-local="union del este"')
            ->toContain('data-visitante="federal misiones"')
            ->toContain('Interzonal')
            ->toContain('tarjeta-compacta--interzonal');
    });
});
