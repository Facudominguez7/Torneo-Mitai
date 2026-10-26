<?php

use App\Models\ConfiguracionCobroPlanilla;
use App\Models\HistorialCobroPlanilla;
use App\Models\User;
use App\Services\Cobros\InicioCreditoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/FixturesCargos.php';

/**
 * Evento histórico de auditoría con la huella del dominio crédito, sin
 * entidad financiera de origen: el inicio no valida pagos ni cargos reales.
 */
function crearEventoCreditoInicio(User $admin, string $clave, int $pagoId = 37, int $cargoId = 41, string $accion = 'aplicar_credito'): HistorialCobroPlanilla
{
    return HistorialCobroPlanilla::create([
        'accion' => $accion,
        'usuario_id' => (int) $admin->id,
        'actor_nombre' => $admin->name,
        'valores_antes' => ['pago_id' => $pagoId, 'cargo_id' => $cargoId, 'clave_idempotencia' => $clave],
        'valores_despues' => ['aplicaciones' => [], 'importe_aplicado_ars' => 1000, 'credito_restante_ars' => 0],
    ]);
}

beforeEach(function (): void {
    $this->admin = crearAdminCargos('Operador Inicio');
    activarCobrosCargos();
    $this->servicio = new InicioCreditoService;
    $this->clave = 'ca11a111-1111-41a1-8a1a-111111111111';
    $this->pagoId = 37;
    $this->cargoId = 41;
});

it('prepara la operacion nueva con actor fresco clave normalizada y evento nulo', function (): void {
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);

    $resultado = $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, strtoupper($this->clave));

    expect($resultado['evento'])->toBeNull()
        ->and($resultado['clave'])->toBe($this->clave)
        ->and($resultado['actor'])->toBeInstanceOf(User::class)
        ->and($resultado['actor']->id)->toBe($this->admin->id)
        ->and($resultado['actor']->name)->toBe('Nombre Vigente')
        ->and($resultado['actor']->rol)->toBe('admin')
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('devuelve el evento original en replay hexadecimal aun con filas historicas ajenas', function (): void {
    $original = crearEventoCreditoInicio($this->admin, strtoupper($this->clave), $this->pagoId, $this->cargoId);
    crearEventoCreditoInicio($this->admin, 'de11a111-1111-41a1-8a1a-111111111111', 50, 60);
    crearEventoCreditoInicio($this->admin, strtoupper($this->clave), $this->pagoId, $this->cargoId, 'activar_cobros');

    $replay = $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave);

    expect($replay['evento']->is($original))->toBeTrue()
        ->and($replay['evento']->getKey())->toBe($original->getKey())
        ->and($replay['clave'])->toBe($this->clave)
        ->and(HistorialCobroPlanilla::query()->where('accion', 'aplicar_credito')->count())->toBe(2)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(3);
});

it('rechaza la clave reutilizada con otra tripleta sin ningun efecto nuevo', function (string $cambio): void {
    crearEventoCreditoInicio($this->admin, $this->clave, $this->pagoId, $this->cargoId);
    [$pagoId, $cargoId, $actor] = match ($cambio) {
        'cargo' => [$this->pagoId, 42, $this->admin],
        'pago' => [38, $this->cargoId, $this->admin],
        'actor' => [$this->pagoId, $this->cargoId, crearAdminCargos('Operador Alterno')],
    };

    expect(fn () => $this->servicio->iniciar($actor, $pagoId, $cargoId, $this->clave))->toThrow(ValidationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
})->with(['cargo', 'pago', 'actor']);

it('rechaza una huella malformada con uuid coincidente sin alterar el historial', function (array $huella): void {
    $evento = crearEventoCreditoInicio($this->admin, $this->clave);
    $valoresAntes = ['clave_idempotencia' => $this->clave] + $huella;
    HistorialCobroPlanilla::query()->whereKey($evento->id)->update([
        'valores_antes' => json_encode($valoresAntes, JSON_THROW_ON_ERROR),
    ]);
    $antes = $evento->fresh()->getRawOriginal();

    expect(fn () => $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave))
        ->toThrow(ValidationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1)
        ->and($evento->fresh()->getRawOriginal())->toBe($antes);
})->with([
    'pago ausente' => [['cargo_id' => 41]],
    'cargo ausente' => [['pago_id' => 37]],
    'pago nulo' => [['pago_id' => null, 'cargo_id' => 41]],
    'cargo nulo' => [['pago_id' => 37, 'cargo_id' => null]],
    'pago string' => [['pago_id' => '37', 'cargo_id' => 41]],
    'cargo string' => [['pago_id' => 37, 'cargo_id' => '41']],
    'pago decimal' => [['pago_id' => 37.5, 'cargo_id' => 41]],
    'cargo decimal' => [['pago_id' => 37, 'cargo_id' => 41.5]],
]);

it('no reclama un uuid usado por otra accion del historial', function (): void {
    crearEventoCreditoInicio($this->admin, $this->clave, $this->pagoId, $this->cargoId, 'activar_cobros');

    $resultado = $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave);

    expect($resultado['evento'])->toBeNull()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
});

it('rechaza actores que no son administradores persistidos y vigentes', function (string $caso): void {
    $actor = match ($caso) {
        'transitorio' => (new User)->forceFill(['id' => $this->admin->id, 'rol' => 'admin']),
        'pk_fingida' => tap($this->admin, fn ($usuario) => $usuario->forceFill(['id' => 999999999])),
        'regular' => crearUsuarioConRolCargos('regular'),
        'editor' => crearUsuarioConRolCargos('editor'),
        'rol_falsificado' => tap(crearUsuarioConRolCargos('regular'), fn ($usuario) => $usuario->forceFill(['rol' => 'admin'])),
        'revocado' => tap($this->admin, fn () => User::query()->whereKey($this->admin->id)->update(['rol' => 'editor'])),
        'borrado' => tap($this->admin, fn () => User::query()->whereKey($this->admin->id)->delete()),
    };

    expect(fn () => $this->servicio->iniciar($actor, $this->pagoId, $this->cargoId, $this->clave))->toThrow(AuthorizationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['transitorio', 'pk_fingida', 'regular', 'editor', 'rol_falsificado', 'revocado', 'borrado']);

it('usa la verdad fresca de la base aunque el actor local este desactualizado', function (): void {
    User::query()->whereKey($this->admin->id)->update(['name' => 'Nombre Vigente']);
    $this->admin->rol = 'regular';
    $this->admin->name = 'Nombre Viejo';

    $resultado = $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave);

    expect($resultado['actor'])->not->toBe($this->admin)
        ->and($resultado['actor']->name)->toBe('Nombre Vigente')
        ->and($resultado['actor']->rol)->toBe('admin')
        ->and($resultado['evento'])->toBeNull();
});

it('falla cerrado sin configuracion global activa sin crear la fila', function (string $caso): void {
    $caso === 'ausente'
        ? ConfiguracionCobroPlanilla::query()->whereKey(1)->delete()
        : ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null]);

    expect(fn () => $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave))->toThrow(RuntimeException::class)
        ->and(ConfiguracionCobroPlanilla::query()->count())->toBe($caso === 'ausente' ? 0 : 1)
        ->and(ConfiguracionCobroPlanilla::query()->value('activado_at'))->toBeNull()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with(['ausente', 'inactiva']);

it('rechaza identificadores no positivos y claves que no son uuid', function (int $pagoId, int $cargoId, string $clave): void {
    expect(fn () => $this->servicio->iniciar($this->admin, $pagoId, $cargoId, $clave))->toThrow(ValidationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
})->with([
    'pago cero' => [0, 41, 'ca11a111-1111-41a1-8a1a-111111111111'],
    'pago negativo' => [-5, 41, 'ca11a111-1111-41a1-8a1a-111111111111'],
    'cargo cero' => [37, 0, 'ca11a111-1111-41a1-8a1a-111111111111'],
    'cargo negativo' => [37, -5, 'ca11a111-1111-41a1-8a1a-111111111111'],
    'clave invalida' => [37, 41, 'no-uuid'],
]);

it('autoriza y exige configuracion antes de resolver el replay', function (string $caso): void {
    crearEventoCreditoInicio($this->admin, $this->clave, $this->pagoId, $this->cargoId);
    $caso === 'config_inactiva'
        ? ConfiguracionCobroPlanilla::query()->whereKey(1)->update(['activado_at' => null])
        : User::query()->whereKey($this->admin->id)->update(['rol' => 'editor']);

    expect(fn () => $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave))
        ->toThrow($caso === 'config_inactiva' ? RuntimeException::class : AuthorizationException::class)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(1);
})->with(['config_inactiva', 'actor_revocado']);

it('emite solo tres lecturas bloqueadas sin escrituras en llamadas repetidas', function (): void {
    $consultas = [];
    $conexion = DB::connection();
    $dispatcher = $conexion->getEventDispatcher();
    $conexion->setEventDispatcher(clone $dispatcher);
    try {
        $conexion->listen(function (QueryExecuted $consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });
        $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave);
        $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave);
    } finally {
        $conexion->setEventDispatcher($dispatcher);
    }

    expect($consultas)->toHaveCount(6);
    foreach ($consultas as $sql) {
        expect($sql)->toStartWith('select')->toContain('for update');
    }
    expect(str_contains($consultas[0], '`users`'))->toBeTrue()
        ->and(str_contains($consultas[1], '`configuracion_cobro_planillas`'))->toBeTrue()
        ->and(str_contains($consultas[2], '`historial_cobro_planillas`'))->toBeTrue()
        ->and(str_contains($consultas[4], '`configuracion_cobro_planillas`'))->toBeTrue()
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});

it('exige la transaccion externa del llamador antes de consultar', function (): void {
    // Mock del facade sólo para cubrir la rama sin transacción: RefreshDatabase
    // ya abre la TX externa y no hay conexiones reales en carrera durante el swap.
    $original = DB::getFacadeRoot();
    DB::swap(new class
    {
        public function transactionLevel(): int
        {
            return 0;
        }
    });
    try {
        expect(fn () => $this->servicio->iniciar($this->admin, $this->pagoId, $this->cargoId, $this->clave))->toThrow(LogicException::class);
    } finally {
        DB::swap($original);
    }

    expect(DB::getFacadeRoot())->toBe($original)
        ->and(HistorialCobroPlanilla::query()->count())->toBe(0);
});
