<?php

use App\Models\CargoPlanilla;
use App\Models\EquipoEdicion;
use App\Models\Partido;
use App\Models\PoliticaExencionCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\PagosCobroService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/FixturesCargos.php';

beforeEach(function (): void {
    $this->contexto = crearContextoCargos();
    $this->equipo = crearEquipoCargos($this->contexto, 'Equipo Politica');
    $this->admin = crearAdminCargos();
    $this->datos = [
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'usuario_id' => $this->admin->id,
    ];
});

it('persiste una politica inerte con relaciones legacy explicitas', function (): void {
    $politica = PoliticaExencionCobroPlanilla::create($this->datos)->fresh();
    foreach (['equipo' => ['idEquipo', $this->equipo], 'edicion' => ['idEdicion', $this->contexto['edicion']], 'usuario' => ['usuario_id', $this->admin]] as $nombre => [$clave, $modelo]) {
        expect($politica->$nombre()->getForeignKeyName())->toBe($clave)
            ->and($politica->$nombre()->getOwnerKeyName())->toBe('id')
            ->and($politica->$nombre->is($modelo))->toBeTrue();
    }
    expect($politica->idEquipo)->toBeInt()->toBe($this->equipo->id)
        ->and($politica->actor_nombre)->toBe('Admin Cobros')
        ->and($politica->getAttributes())->not->toHaveKeys(['updated_at', 'importe_ars', 'saldo']);
    $this->assertDatabaseHas('configuracion_cobro_planillas', ['id' => 1, 'activado_at' => null]);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
});

it('acepta motivo opcional literal sin normalizar', function (?string $motivo): void {
    $politica = PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['motivo' => $motivo]));
    expect($politica->fresh()->motivo)->toBe($motivo);
})->with([null, '', "  Acuerdo\n literal  ", str_repeat('a', 300)]);

it('rechaza identificadores crudos fuera del entero positivo firmado', function (string $campo, mixed $valor): void {
    expect(fn () => PoliticaExencionCobroPlanilla::create(array_replace($this->datos, [$campo => $valor])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
})->with(['idEquipo', 'idEdicion', 'usuario_id'])->with([true, false, null, '1', 1.0, 1.5, 0, -1, 2147483648, [[]]]);

it('requiere equipo y edicion existentes', function (string $campo): void {
    expect(fn () => PoliticaExencionCobroPlanilla::create(array_replace($this->datos, [$campo => 2147483647])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
})->with(['idEquipo', 'idEdicion']);

it('requiere inscripcion del equipo en la edicion indicada', function (): void {
    $otro = crearContextoCargos('Otra Edicion');
    expect(fn () => PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['idEdicion' => $otro['edicion']->id])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
});

it('acepta inscripciones duplicadas sin exigir un unico pivot', function (): void {
    EquipoEdicion::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id]);
    PoliticaExencionCobroPlanilla::create($this->datos);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 1);
});

it('requiere administrador existente y vigente al crear', function (string $rol): void {
    $id = $rol === 'ausente' ? 2147483647 : crearUsuarioConRolCargos($rol)->id;
    expect(fn () => PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['usuario_id' => $id])))->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
})->with(['editor', 'regular', 'ausente']);

it('rechaza actor demovido aunque la instancia anterior sea admin', function (): void {
    User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);
    expect($this->admin->isAdmin())->toBeTrue();
    expect(fn () => PoliticaExencionCobroPlanilla::create($this->datos))->toThrow(AuthorizationException::class);
});

it('captura nombre actual y tiempo del servidor ignorando atributos falsificados', function (): void {
    $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);
    $politica = new PoliticaExencionCobroPlanilla($this->datos);
    $politica->setRelation('usuario', $this->admin);
    $politica->forceFill(['actor_nombre' => 'Falso', 'created_at' => '2000-01-01'])->save();
    User::query()->whereKey($this->admin->id)->update(['name' => 'Otro', 'rol' => 'editor']);
    $politica = $politica->fresh();
    expect($politica->actor_nombre)->toBe('Nombre Vigente')
        ->and($politica->created_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($politica->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-11 12:00:00')
        ->and($politica->usuario->isAdmin())->toBeFalse();
});

it('rechaza motivos de tipos no textuales', function (mixed $motivo): void {
    expect(fn () => PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['motivo' => $motivo])))->toThrow(LogicException::class);
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 0);
})->with([true, 1, 1.5, [[]]]);

it('garantiza unicidad SQL del par pero admite otro equipo y otra edicion', function (): void {
    $politica = PoliticaExencionCobroPlanilla::create($this->datos);
    expect(fn () => PoliticaExencionCobroPlanilla::create($this->datos))->toThrow(QueryException::class);
    $fila = $politica->getAttributes();
    unset($fila['id']);
    expect(fn () => DB::table('politica_exencion_cobro_planillas')->insert($fila))->toThrow(QueryException::class);
    $otroEquipo = crearEquipoCargos($this->contexto, 'Otro Equipo');
    PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['idEquipo' => $otroEquipo->id]));
    $otra = crearContextoCargos('Otra');
    EquipoEdicion::create(['idEquipo' => $this->equipo->id, 'idEdicion' => $otra['edicion']->id]);
    PoliticaExencionCobroPlanilla::create(array_replace($this->datos, ['idEdicion' => $otra['edicion']->id]));
    $this->assertDatabaseCount('politica_exencion_cobro_planillas', 3);
});

it('rechaza editar y borrar por instancia conservando la fila', function (bool $borrar): void {
    $politica = PoliticaExencionCobroPlanilla::create($this->datos)->fresh();
    $antes = $politica->getAttributes();
    expect(fn () => $borrar ? $politica->delete() : $politica->update(['motivo' => 'Cambio']))->toThrow(LogicException::class);
    expect($politica->fresh()->getAttributes())->toBe($antes);
})->with([true, false]);

it('preserva snapshot historico al eliminar por SQL al otorgante', function (): void {
    $politica = PoliticaExencionCobroPlanilla::create($this->datos)->fresh();
    $antes = $politica->getAttributes();
    DB::table('users')->where('id', $this->admin->id)->delete();
    expect($politica->fresh()->getAttributes())->toBe(array_replace($antes, ['usuario_id' => null]))
        ->and($politica->fresh()->usuario)->toBeNull();
});

it('no borra la politica cuando desaparecen equipo y edicion legacy', function (): void {
    $politica = PoliticaExencionCobroPlanilla::create($this->datos)->fresh();
    $antes = $politica->getAttributes();
    DB::table('equipos')->where('id', $this->equipo->id)->delete();
    DB::table('ediciones')->where('id', $this->contexto['edicion']->id)->delete();
    expect($politica->fresh()->getAttributes())->toBe($antes)
        ->and($politica->fresh()->equipo)->toBeNull()
        ->and($politica->fresh()->edicion)->toBeNull();
});

it('preserva finanzas no vacias y no materializa exenciones ni cargos futuros', function (): void {
    activarCobrosCargos();
    $rival = crearEquipoCargos($this->contexto, 'Rival');
    $partido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rival));
    $datosCargo = [
        'tipo' => CargoPlanilla::TIPO_PARTIDO, 'parent_type' => Partido::class, 'parent_id' => $partido->id,
        'idEquipo' => $this->equipo->id, 'idEdicion' => $this->contexto['edicion']->id,
        'idCategoria' => $this->contexto['categoria']->id, 'precio_original' => 25000, 'snapshot' => [],
    ];
    $cargo = CargoPlanilla::create($datosCargo);
    app(PagosCobroService::class)->registrarPago($this->admin, $cargo->id, 10000, 'efectivo', (string) Str::uuid());
    $leer = fn (string $tabla): array => DB::table($tabla)->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all();
    $antes = [];
    foreach (['cargo_planillas', 'pago_planillas', 'aplicacion_pago_planillas', 'historial_cobro_planillas', 'cuenta_cobro_planillas', 'configuracion_cobro_planillas'] as $tabla) {
        $antes[$tabla] = $leer($tabla);
        expect($antes[$tabla])->not->toBeEmpty();
    }
    PoliticaExencionCobroPlanilla::create($this->datos);
    foreach ($antes as $tabla => $filas) {
        expect($leer($tabla))->toBe($filas);
    }
    $nuevoPartido = Partido::create(crearDatosPartidoCargos($this->contexto, $this->equipo, $rival));
    $nuevo = CargoPlanilla::create(array_replace($datosCargo, ['parent_id' => $nuevoPartido->id]));
    expect($nuevo->fresh()->precio_original)->toBe(25000);
    $this->assertDatabaseCount('exencion_cobro_planillas', 0);
});
