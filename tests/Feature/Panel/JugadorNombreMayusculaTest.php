<?php

// Nombre y apellido de los jugadores se guardan con la inicial de cada palabra en mayúscula.

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\EquipoEdicion;
use App\Models\Jugador;
use App\Models\ListaBuenaFe;
use App\Models\User;
use App\Services\ActualizadorIdentidadJugador;

beforeEach(function (): void {
    $this->withSession(['_token' => 'nombre-mayuscula'])->withHeaders(['X-CSRF-TOKEN' => 'nombre-mayuscula']);
});

function contextoNombreMayuscula(): array
{
    $admin = User::factory()->create(['rol' => 'admin', 'email_verified_at' => now()]);
    $edicion = Edicion::create(['nombre' => 'Edicion Mayusculas']);
    $categoria = Categoria::create(['idEdicion' => $edicion->id, 'nombreCategoria' => 'Sub 12']);
    $equipo = Equipo::create(['idCategoria' => $categoria->id, 'idEdicion' => $edicion->id, 'nombre' => 'Mitai Verde', 'foto' => 'equipo.png']);
    EquipoEdicion::create(['idEdicion' => $edicion->id, 'idEquipo' => $equipo->id, 'idCategoria' => $categoria->id]);

    return compact('admin', 'edicion', 'categoria', 'equipo');
}

function jugadorNombreMayuscula(string $dni, string $nombre, string $apellido): Jugador
{
    return Jugador::create([
        'dni' => $dni, 'nombre' => $nombre, 'apellido' => $apellido,
        'goles_totales' => 3, 'partidos_totales' => 5, 'tarjetas_rojas_totales' => 1,
    ]);
}

it('guarda con mayuscula inicial al cargar un jugador en la lista de buena fe', function (): void {
    $contexto = contextoNombreMayuscula();

    $this->actingAs($contexto['admin'])->post(route('lista-buena-fe.agregar'), [
        'dni_jugador' => '41222333', 'nombre_jugador' => 'juan pablo', 'apellido_jugador' => 'GÓMEZ díaz',
        'fecha_nacimiento' => '2014-05-10', 'idEquipo' => $contexto['equipo']->id,
        'idEdicion' => $contexto['edicion']->id, 'idCategoria' => $contexto['categoria']->id,
    ])->assertRedirect()->assertSessionHas('success', 'Jugador agregado correctamente.');

    expect(Jugador::query()->findOrFail('41222333')->only(['nombre', 'apellido']))->toBe(['nombre' => 'Juan Pablo', 'apellido' => 'Gómez Díaz'])
        ->and(ListaBuenaFe::query()->where('dni_jugador', '41222333')->where('activo', true)->count())->toBe(1);
});

it('normaliza nombre y apellido con la regla elegida', function (string $escrito, string $guardado): void {
    $jugador = jugadorNombreMayuscula('40111222', $escrito, $escrito);

    expect($jugador->fresh()->only(['nombre', 'apellido']))->toBe(['nombre' => $guardado, 'apellido' => $guardado]);
})->with([
    'minúsculas' => ['juan pablo', 'Juan Pablo'],
    'mayúsculas' => ['JUAN PABLO', 'Juan Pablo'],
    'con tildes' => ['gómez díaz', 'Gómez Díaz'],
    'partículas' => ['de la fuente', 'De La Fuente'],
    'espacios en los bordes' => ['  mateo  ', 'Mateo'],
    'ya normalizado' => ['Lucía Pérez', 'Lucía Pérez'],
]);

it('normaliza al sincronizar un jugador existente sin tocar sus estadisticas', function (): void {
    jugadorNombreMayuscula('40111222', 'Mateo', 'Perez');

    app(ActualizadorIdentidadJugador::class)->sincronizarJugador('40111222', 'mateo ezequiel', 'PEREZ');

    expect(Jugador::query()->findOrFail('40111222')->only(['nombre', 'apellido', 'goles_totales', 'partidos_totales', 'tarjetas_rojas_totales']))
        ->toBe(['nombre' => 'Mateo Ezequiel', 'apellido' => 'Perez', 'goles_totales' => 3, 'partidos_totales' => 5, 'tarjetas_rojas_totales' => 1]);
});

it('normaliza al corregir el dni de un jugador', function (): void {
    jugadorNombreMayuscula('40111222', 'Mateo', 'Perez');

    app(ActualizadorIdentidadJugador::class)->actualizarIdentidad('40111222', '40111223', 'mateo', 'perez');

    expect(Jugador::query()->findOrFail('40111223')->only(['nombre', 'apellido']))->toBe(['nombre' => 'Mateo', 'apellido' => 'Perez'])
        ->and(Jugador::query()->find('40111222'))->toBeNull();
});
