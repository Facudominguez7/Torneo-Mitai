<?php

declare(strict_types=1);

use App\Services\Cobros\DistribucionCreditoService;

function crearFilaDistribucionCredito(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'precio_original' => 1000,
        'aplicado_ars' => 0,
        'fecha_vigente' => '2026-03-01 10:00:00',
        'padre_valido' => true,
    ], $overrides);
}

function obtenerIdsDistribucionCredito(array $plan): array
{
    return array_column($plan['aplicaciones'], 'cargo_id');
}

it('aplica primero al elegido y reparte el resto por fecha ascendente', function (): void {
    $filas = [
        crearFilaDistribucionCredito(3, ['fecha_vigente' => '2026-01-05 09:00:00']),
        crearFilaDistribucionCredito(7, ['fecha_vigente' => '2026-05-20 09:00:00']),
        crearFilaDistribucionCredito(5, ['fecha_vigente' => '2026-03-01 10:00:00']),
    ];
    $filasOriginales = $filas;

    $plan = (new DistribucionCreditoService)->planificar($filas, 7, 3500);

    expect($plan['aplicaciones'])->toBe([
        ['cargo_id' => 7, 'importe_ars' => 1000],
        ['cargo_id' => 3, 'importe_ars' => 1000],
        ['cargo_id' => 5, 'importe_ars' => 1000],
    ])
        ->and($plan['credito_restante_ars'])->toBe(500)
        ->and($filas)->toBe($filasOriginales);
});

it('omite al elegido saldado y ordena al resto por fecha', function (): void {
    $filas = [
        crearFilaDistribucionCredito(9, ['precio_original' => 500, 'aplicado_ars' => 500, 'padre_valido' => false, 'fecha_vigente' => '2025-01-01 00:00:00']),
        crearFilaDistribucionCredito(4, ['fecha_vigente' => '2026-06-01 00:00:00']),
        crearFilaDistribucionCredito(2, ['fecha_vigente' => '2026-02-01 00:00:00']),
    ];

    $plan = (new DistribucionCreditoService)->planificar($filas, 9, 2000);

    expect(obtenerIdsDistribucionCredito($plan))->toBe([2, 4])
        ->and($plan['credito_restante_ars'])->toBe(0);
});

it('deja los sin fecha al final desempata por id y prioriza al elegido sin fecha', function (): void {
    $filas = [
        crearFilaDistribucionCredito(8, ['fecha_vigente' => null]),
        crearFilaDistribucionCredito(6, ['fecha_vigente' => '   ']),
        crearFilaDistribucionCredito(1, ['fecha_vigente' => '2026-04-01 00:00:00']),
        crearFilaDistribucionCredito(3, ['fecha_vigente' => '2026-04-01 00:00:00']),
    ];

    $plan = (new DistribucionCreditoService)->planificar($filas, 8, 4000);

    expect(obtenerIdsDistribucionCredito($plan))->toBe([8, 1, 3, 6])
        ->and($plan['credito_restante_ars'])->toBe(0);
});

it('cambia el orden exacto cuando cambia la fecha vigente', function (): void {
    $filas = [
        crearFilaDistribucionCredito(61, ['fecha_vigente' => '2026-05-01 00:00:00']),
        crearFilaDistribucionCredito(62, ['fecha_vigente' => '2026-06-01 00:00:00']),
        crearFilaDistribucionCredito(63, ['fecha_vigente' => '2026-07-01 00:00:00']),
    ];
    $servicio = new DistribucionCreditoService;

    $planInicial = $servicio->planificar($filas, 63, 3000);
    $filas[0]['fecha_vigente'] = '2026-09-01 00:00:00';
    $planConFechaCambiada = $servicio->planificar($filas, 63, 3000);

    expect(obtenerIdsDistribucionCredito($planInicial))->toBe([63, 61, 62])
        ->and(obtenerIdsDistribucionCredito($planConFechaCambiada))->toBe([63, 62, 61]);
});

it('ignora propiedades extra como fecha_snapshot sin mutar la entrada', function (): void {
    $filas = [
        array_merge(crearFilaDistribucionCredito(11, ['fecha_vigente' => '2026-07-01 00:00:00']), ['fecha_snapshot' => '2020-01-01 00:00:00']),
        array_merge(crearFilaDistribucionCredito(12, ['fecha_vigente' => '2026-08-01 00:00:00']), ['fecha_snapshot' => '2020-01-01 00:00:00']),
    ];
    $filasOriginales = $filas;

    $plan = (new DistribucionCreditoService)->planificar($filas, 11, 5000);

    expect(obtenerIdsDistribucionCredito($plan))->toBe([11, 12])
        ->and($plan['credito_restante_ars'])->toBe(3000)
        ->and($filas)->toBe($filasOriginales);
});

it('reparte en parciales cuando el credito no alcanza la deuda total', function (): void {
    $filas = [
        crearFilaDistribucionCredito(21, ['precio_original' => 3000, 'aplicado_ars' => 500, 'fecha_vigente' => '2026-09-01 00:00:00']),
        crearFilaDistribucionCredito(22, ['fecha_vigente' => '2026-10-01 00:00:00']),
    ];

    $plan = (new DistribucionCreditoService)->planificar($filas, 21, 3000);

    expect($plan['aplicaciones'])->toBe([
        ['cargo_id' => 21, 'importe_ars' => 2500],
        ['cargo_id' => 22, 'importe_ars' => 500],
    ])
        ->and($plan['credito_restante_ars'])->toBe(0);
});

it('aplica un parcial al elegido cuando el credito es menor que su deuda', function (): void {
    $filas = [
        crearFilaDistribucionCredito(21, ['precio_original' => 3000, 'aplicado_ars' => 500, 'fecha_vigente' => '2026-09-01 00:00:00']),
        crearFilaDistribucionCredito(22, ['fecha_vigente' => '2026-10-01 00:00:00']),
    ];

    $plan = (new DistribucionCreditoService)->planificar($filas, 21, 1500);

    expect($plan['aplicaciones'])->toBe([
        ['cargo_id' => 21, 'importe_ars' => 1500],
    ])
        ->and($plan['credito_restante_ars'])->toBe(0);
});

it('no genera aplicaciones y conserva el credito cuando todo esta saldado', function (): void {
    $filas = [
        crearFilaDistribucionCredito(31, ['precio_original' => 800, 'aplicado_ars' => 800]),
        crearFilaDistribucionCredito(32, ['precio_original' => 400, 'aplicado_ars' => 400, 'padre_valido' => false, 'fecha_vigente' => null]),
    ];

    $plan = (new DistribucionCreditoService)->planificar($filas, 31, 2147483647);

    expect($plan)->toBe(['aplicaciones' => [], 'credito_restante_ars' => 2147483647]);
});

it('falla cerrado ante filas deudoras corruptas', function (array $overrides): void {
    $filas = [
        crearFilaDistribucionCredito(41, $overrides),
        crearFilaDistribucionCredito(42),
    ];

    expect(fn (): array => (new DistribucionCreditoService)->planificar($filas, 42, 1000))
        ->toThrow(RuntimeException::class);
})->with([
    'aplicado negativo' => [['aplicado_ars' => -1]],
    'aplicado mayor que el precio' => [['aplicado_ars' => 1001]],
    'precio invalido' => [['precio_original' => 0]],
    'precio con tipo invalido' => [['precio_original' => '1000']],
    'precio por encima del entero permitido' => [['precio_original' => 2147483648]],
    'deudor sin padre valido' => [['padre_valido' => false]],
    'deudor sin padre declarado' => [['padre_valido' => null]],
    'fecha ilegible' => [['fecha_vigente' => '32/13/2026']],
    'fecha con tipo ilegal' => [['fecha_vigente' => 20260401]],
]);

it('rechaza entradas invalidas con InvalidArgumentException', function (array $filas, int $cargoId, int $credito): void {
    expect(fn (): array => (new DistribucionCreditoService)->planificar($filas, $cargoId, $credito))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'credito en cero' => [[crearFilaDistribucionCredito(51)], 51, 0],
    'credito negativo' => [[crearFilaDistribucionCredito(51)], 51, -100],
    'credito por encima del entero permitido' => [[crearFilaDistribucionCredito(51)], 51, 2147483648],
    'cargo elegido no positivo' => [[crearFilaDistribucionCredito(51)], 0, 500],
    'cargo elegido ausente' => [[crearFilaDistribucionCredito(51)], 99, 500],
    'ids duplicados' => [[crearFilaDistribucionCredito(52), crearFilaDistribucionCredito(52)], 52, 500],
    'id de fila invalido' => [[crearFilaDistribucionCredito(-5), crearFilaDistribucionCredito(53)], 53, 500],
]);
