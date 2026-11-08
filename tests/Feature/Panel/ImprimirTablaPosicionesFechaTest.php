<?php

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Fecha;
use App\Models\Grupo;
use App\Models\Partido;
use App\Models\TablaPosicion;
use App\Models\User;

function crearContextoImpresionTablaPosiciones(string $nombreEdicion = 'Edicion Tablas Fecha'): array
{
    $admin = User::factory()->create([
        'rol' => 'admin',
        'email_verified_at' => now(),
    ]);

    $edicion = Edicion::create([
        'nombre' => $nombreEdicion,
    ]);

    $categoria = Categoria::create([
        'idEdicion' => $edicion->id,
        'nombreCategoria' => 'Sub 12',
    ]);

    $grupo = Grupo::create([
        'idCategoria' => $categoria->id,
        'idEdicion' => $edicion->id,
        'nombre' => 'Grupo A',
    ]);

    return compact('admin', 'edicion', 'categoria', 'grupo');
}

function fechaImpresionTabla(array $contexto, string $nombre): Fecha
{
    return Fecha::create([
        'idCategoria' => null,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombre,
    ]);
}

function partidoImpresionTabla(
    array $contexto,
    Fecha $fecha,
    ?string $horario,
    int $jugado = 0,
): Partido {
    static $secuencia = 0;
    $secuencia++;

    $equipoLocal = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => "Local Impresion {$secuencia}",
        'foto' => 'local.png',
    ]);

    $equipoVisitante = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => "Visita Impresion {$secuencia}",
        'foto' => 'visita.png',
    ]);

    return Partido::create([
        'idCategoria' => $contexto['categoria']->id,
        'idFechas' => $fecha->id,
        'idEquipoLocal' => $equipoLocal->id,
        'idEquipoVisitante' => $equipoVisitante->id,
        'idGrupo' => $contexto['grupo']->id,
        'idEdicion' => $contexto['edicion']->id,
        'golesEquipoLocal' => $jugado === 1 ? 2 : null,
        'golesEquipoVisitante' => $jugado === 1 ? 1 : null,
        'horario_datetime' => $horario,
        'cancha' => 1,
        'jugado' => $jugado,
    ]);
}

it('muestra la fecha con resultados cargados en el encabezado de la impresion', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();
    $fechaJugada = fechaImpresionTabla($contexto, 'Fecha 3');

    partidoImpresionTabla($contexto, $fechaJugada, '2026-04-11 09:00:00', jugado: 1);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Fecha de resultados: Fecha 3', false)
        ->assertSee('size: A4 portrait', false);
});

it('elige la fecha del mayor horario jugado aunque los ids sugieran lo contrario', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();

    $fechaConHorarioReciente = fechaImpresionTabla($contexto, 'Fecha Reciente');
    $fechaConHorarioAntiguo = fechaImpresionTabla($contexto, 'Fecha Antigua');

    // La fecha antigua se crea después (mayor idFechas), pero su partido jugado es más viejo.
    partidoImpresionTabla($contexto, $fechaConHorarioAntiguo, '2026-03-01 09:00:00', jugado: 1);
    partidoImpresionTabla($contexto, $fechaConHorarioReciente, '2026-04-20 09:00:00', jugado: 1);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Fecha de resultados: Fecha Reciente', false)
        ->assertDontSee('Fecha de resultados: Fecha Antigua', false);
});

it('ignora fechas sin jugar mas nuevas y resultados de otras ediciones', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();
    $otraEdicion = crearContextoImpresionTablaPosiciones('Edicion Otra Temporada');

    $fechaJugada = fechaImpresionTabla($contexto, 'Fecha Cargada');
    $fechaFutura = fechaImpresionTabla($contexto, 'Fecha Futura');

    partidoImpresionTabla($contexto, $fechaJugada, '2026-04-11 09:00:00', jugado: 1);
    // Ronda más nueva aún sin resultados: no debe definir la etiqueta.
    partidoImpresionTabla($contexto, $fechaFutura, '2026-05-30 09:00:00');

    $fechaOtraEdicion = fechaImpresionTabla($otraEdicion, 'Fecha Otra Edicion');
    partidoImpresionTabla($otraEdicion, $fechaOtraEdicion, '2026-06-15 09:00:00', jugado: 1);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Fecha de resultados: Fecha Cargada', false)
        ->assertDontSee('Fecha Futura')
        ->assertDontSee('Fecha Otra Edicion');
});

it('prioriza el horario conocido sobre filas legacy sin datetime', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();

    $fechaConHorario = fechaImpresionTabla($contexto, 'Fecha Con Horario');
    $fechaLegacy = fechaImpresionTabla($contexto, 'Fecha Legacy');

    // La fila legacy sin datetime tiene mayor idFechas: el horario conocido manda.
    partidoImpresionTabla($contexto, $fechaLegacy, null, jugado: 1);
    partidoImpresionTabla($contexto, $fechaConHorario, '2026-04-11 09:00:00', jugado: 1);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Fecha de resultados: Fecha Con Horario', false)
        ->assertDontSee('Fecha de resultados: Fecha Legacy', false);
});

it('usa el mayor idFechas como desempate cuando todos los jugados carecen de horario', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();

    $fechaInicial = fechaImpresionTabla($contexto, 'Fecha Inicial');
    $fechaUltima = fechaImpresionTabla($contexto, 'Fecha Ultima');

    partidoImpresionTabla($contexto, $fechaInicial, null, jugado: 1);
    partidoImpresionTabla($contexto, $fechaUltima, null, jugado: 1);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Fecha de resultados: Fecha Ultima', false)
        ->assertDontSee('Fecha de resultados: Fecha Inicial', false);
});

function filaTablaImpresion(array $contexto, string $nombreEquipo, bool $inscripto, int $idEdicionFila): TablaPosicion
{
    $equipo = Equipo::create([
        'idCategoria' => $contexto['categoria']->id,
        'idEdicion' => $contexto['edicion']->id,
        'nombre' => $nombreEquipo,
        'foto' => 'equipo.png',
    ]);

    if ($inscripto) {
        EquipoEdicion::create([
            'idEquipo' => $equipo->id,
            'idEdicion' => $contexto['edicion']->id,
            'idCategoria' => $contexto['categoria']->id,
        ]);
    }

    return TablaPosicion::create([
        'idGrupo' => $contexto['grupo']->id,
        'idEquipo' => $equipo->id,
        'idEdicion' => $idEdicionFila,
        'puntos' => 0,
        'golesFavor' => 0,
        'golesContra' => 0,
        'diferenciaGoles' => 0,
        'jugado' => 0,
        'ganado' => 0,
        'perdido' => 0,
        'empatado' => 0,
    ]);
}

it('no imprime equipos que ya no estan inscriptos en la edicion', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones('Edicion Eliminados');

    filaTablaImpresion($contexto, 'Equipo Sigue Inscripto', inscripto: true, idEdicionFila: $contexto['edicion']->id);
    filaTablaImpresion($contexto, 'Equipo Borrado De La Edicion', inscripto: false, idEdicionFila: $contexto['edicion']->id);

    $response = $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]));

    $response->assertOk()
        ->assertSee('Equipo Sigue Inscripto', false)
        ->assertDontSee('Equipo Borrado De La Edicion', false);
});

it('mantiene las filas legacy con idEdicion en cero de equipos que siguen inscriptos', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones('Edicion Legacy Cero');

    filaTablaImpresion($contexto, 'Equipo Legacy Inscripto', inscripto: true, idEdicionFila: 0);

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertSee('Equipo Legacy Inscripto', false);
});

it('renderiza sin etiqueta de fecha cuando la edicion no tiene resultados cargados', function (): void {
    $contexto = crearContextoImpresionTablaPosiciones();
    $fechaPendiente = fechaImpresionTabla($contexto, 'Fecha Pendiente');

    partidoImpresionTabla($contexto, $fechaPendiente, '2026-04-11 09:00:00');

    $this->actingAs($contexto['admin'])
        ->get(route('admin.imprimir-tabla-posiciones', ['idEdicion' => $contexto['edicion']->id]))
        ->assertOk()
        ->assertDontSee('Fecha de resultados')
        ->assertSee('Tablas de Posiciones');
});
