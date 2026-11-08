<?php

use App\Models\Fecha;
use App\Models\Partido;
use App\Services\Cobros\ProgramacionCobroService;

require_once __DIR__.'/../Services/Cobros/FixturesCargos.php';

// Alta de partido con fecha nueva escrita en el mismo formulario: la fecha se
// crea para la edición elegida y el partido la usa; si el alta falla, la fecha
// no queda creada.

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = crearAdminCargos();
    $this->contexto = crearContextoCargos('Edicion Fecha Nueva');
    $this->local = crearEquipoCargos($this->contexto, 'Local Fecha');
    $this->visitante = crearEquipoCargos($this->contexto, 'Visitante Fecha');
    $this->actingAs($this->admin)->withSession(['_token' => 'fecha-nueva'])
        ->withHeaders(['X-CSRF-TOKEN' => 'fecha-nueva']);
});

function datosAltaFechaNueva(object $test, array $overrides = []): array
{
    $datos = crearDatosPartidoCargos($test->contexto, $test->local, $test->visitante, ['cancha' => '1']);

    return array_merge($datos, $overrides);
}

it('crea la fecha nueva desde el alta y la usa en el partido', function () {
    $respuesta = $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'idFechas' => '',
        'nombreFecha' => 'Fecha 7',
    ]));

    $respuesta->assertSessionHasNoErrors();

    $fecha = Fecha::query()->where('nombre', 'Fecha 7')->sole();
    expect((int) $fecha->idEdicion)->toBe((int) $this->contexto['edicion']->id)
        ->and(Partido::query()->sole()->idFechas)->toBe((int) $fecha->id);
});

it('usa la fecha elegida cuando no se escribe una nueva', function () {
    $respuesta = $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'nombreFecha' => '',
    ]));

    $respuesta->assertSessionHasNoErrors();
    expect(Fecha::query()->count())->toBe(1)
        ->and(Partido::query()->sole()->idFechas)->toBe((int) $this->contexto['fecha']->id);
});

it('prefiere la fecha nueva cuando se escriben las dos', function () {
    $respuesta = $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'nombreFecha' => 'Fecha 8',
    ]));

    $respuesta->assertSessionHasNoErrors();
    $fecha = Fecha::query()->where('nombre', 'Fecha 8')->sole();
    expect(Partido::query()->sole()->idFechas)->toBe((int) $fecha->id);
});

it('exige una fecha existente o un nombre nuevo', function () {
    $respuesta = $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'idFechas' => '',
        'nombreFecha' => '',
    ]));

    $respuesta->assertSessionHasErrors('idFechas');
    expect(Fecha::query()->count())->toBe(1)
        ->and(Partido::query()->count())->toBe(0);
});

it('rechaza un nombre de fecha demasiado corto sin crear nada', function () {
    $respuesta = $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'idFechas' => '',
        'nombreFecha' => 'F7',
    ]));

    $respuesta->assertSessionHasErrors('nombreFecha');
    expect(Fecha::query()->count())->toBe(1)
        ->and(Partido::query()->count())->toBe(0);
});

it('no deja una fecha huérfana si el alta del partido falla', function () {
    // El alta del partido explota después de resolver la fecha: la transacción
    // debe revertir la fecha recién creada.
    $this->mock(ProgramacionCobroService::class, function ($mock): void {
        $mock->shouldReceive('crearPartido')->andThrow(new RuntimeException('falla simulada'));
    });

    $respuesta = $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('partido.store'), datosAltaFechaNueva($this, [
        'idFechas' => '',
        'nombreFecha' => 'Fecha Huérfana',
    ])))->toThrow(RuntimeException::class);

    expect(Fecha::query()->where('nombre', 'Fecha Huérfana')->exists())->toBeFalse()
        ->and(Partido::query()->count())->toBe(0);
});

it('crea la fecha nueva también en el alta interzonal y un solo partido', function () {
    $datos = datosAltaFechaNueva($this, [
        'idFechas' => '',
        'nombreFecha' => 'Fecha Interzonal',
        'horario_datetime' => null,
        'cancha' => null,
    ]);
    unset($datos['idGrupo']);

    $this->post(route('partido.store-interzonal'), $datos)->assertSessionHasNoErrors();

    $fecha = Fecha::query()->where('nombre', 'Fecha Interzonal')->sole();
    $partido = Partido::query()->sole();

    expect($partido->idFechas)->toBe((int) $fecha->id)
        ->and((bool) $partido->es_interzonal)->toBeTrue();
});
