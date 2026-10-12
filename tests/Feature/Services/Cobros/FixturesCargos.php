<?php

use App\Models\Categoria;
use App\Models\ConfiguracionCobroPlanilla;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fase;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Fixtures del módulo de cobros de planillas (prefijados con "Cargos"
 * para evitar colisiones con helpers globales de otros tests).
 */
function crearContextoCargos(string $nombreEdicion = 'Edicion Cobros'): array
{
    $edicion = Edicion::create(['nombre' => $nombreEdicion]);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12 Cobros']);
    $grupo = Grupo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Grupo A']);
    $fecha = Fecha::create(['idEdicion' => $edicion->id, 'idCategoria' => $categoria->id, 'nombre' => 'Fecha 1']);

    return compact('edicion', 'categoria', 'grupo', 'fecha');
}

function crearEquipoCargos(array $contexto, string $nombre): Equipo
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
    ]);

    return $equipo;
}

function crearAdminCargos(string $nombre = 'Admin Cobros'): User
{
    $admin = User::factory()->create(['name' => $nombre]);
    $admin->forceFill(['rol' => 'admin'])->save();

    return $admin->refresh();
}

function crearUsuarioConRolCargos(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->forceFill(['rol' => $rol])->save();

    return $usuario->refresh();
}

function activarCobrosCargos(string $instante = '2026-06-01 12:00:00'): void
{
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update([
        'activado_at' => Carbon::parse($instante),
    ]);
}

function fijarTarifaCargos(int $tarifa): void
{
    ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['tarifa_ars' => $tarifa]);
}

function crearFaseCargos(string $nombre = 'Final'): Fase
{
    return Fase::create(['nombre' => $nombre]);
}

/**
 * Payload de alta de partido (regular o interzonal según es_interzonal).
 */
function crearDatosPartidoCargos(array $contexto, Equipo $local, Equipo $visitante, array $overrides = []): array
{
    return array_merge([
        'idFechas' => $contexto['fecha']->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'horario_datetime' => '2026-04-05 10:00:00',
        'cancha' => 1,
        'es_interzonal' => false,
    ], $overrides);
}

/**
 * Payload de alta de instancia final.
 */
function crearDatosFinalCargos(array $contexto, Equipo $local, Equipo $visitante, array $overrides = []): array
{
    $fase = $overrides['fase'] ?? crearFaseCargos();

    return array_merge([
        'idFase' => $fase->id,
        'idEquipoLocal' => $local->id,
        'idEquipoVisitante' => $visitante->id,
        'idEdicion' => $contexto['edicion']->id,
        'idCategoria' => $contexto['categoria']->id,
        'horario' => '2026-04-05 10:00:00',
        'cancha' => '1',
    ], array_diff_key($overrides, ['fase' => null]));
}
