<?php

namespace App\Services\Cobros;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Planificador puro de distribución FIFO de un crédito sobre cargos con deuda:
 * el cargo elegido va primero y el resto se ordena por fecha vigente ascendente.
 * No consulta ni persiste base de datos: la capa de cobros carga las filas,
 * valida su contexto y persiste el plan que este servicio devuelve.
 */
class DistribucionCreditoService
{
    private const IMPORTE_MAXIMO = 2147483647;

    /**
     * Calcula el plan de aplicaciones sin mutar las filas recibidas.
     *
     * Cada fila respeta la forma:
     * array{id: int, precio_original: int, aplicado_ars: int, eximido_ars?: int, fecha_vigente: ?string, padre_valido: bool}
     *
     * @param  array<int, array<string, mixed>>  $cargos
     * @param  int  $cargoId  cargo elegido por el usuario
     * @param  int  $credito  crédito disponible en ARS
     * @return array{aplicaciones: list<array{cargo_id: int, importe_ars: int}>, credito_restante_ars: int}
     *
     * @throws InvalidArgumentException si el crédito, el cargo elegido o los ids de fila son inválidos
     * @throws RuntimeException si una fila deudora está corrupta o su fecha vigente es ilegible
     */
    public function planificar(array $cargos, int $cargoId, int $credito): array
    {
        if ($credito < 1 || $credito > self::IMPORTE_MAXIMO) {
            throw new InvalidArgumentException('El crédito debe estar entre 1 y '.self::IMPORTE_MAXIMO.' ARS.');
        }

        if ($cargoId < 1) {
            throw new InvalidArgumentException('El cargo elegido debe ser un id positivo.');
        }

        $deudores = $this->recopilarDeudores($cargos, $cargoId);

        $aplicaciones = [];
        $restante = $credito;

        foreach ($this->ordenarDeudores($deudores, $cargoId) as $id) {
            if ($restante === 0) {
                break;
            }

            $importe = min($restante, $deudores[$id]['deuda']);
            $aplicaciones[] = ['cargo_id' => $id, 'importe_ars' => $importe];
            $restante -= $importe;
        }

        return ['aplicaciones' => $aplicaciones, 'credito_restante_ars' => $restante];
    }

    /**
     * Devuelve las filas con deuda pendiente indexadas por id, validando ids
     * únicos y la integridad de cada deudor. Los saldados se omiten sin exigir
     * padre ni fecha de saldado.
     *
     * @param  array<int, array<string, mixed>>  $cargos
     * @return array<int, array{deuda: int, timestamp: ?int}>
     */
    private function recopilarDeudores(array $cargos, int $cargoId): array
    {
        $deudores = [];
        $idsVistos = [];
        $elegidoPresente = false;

        foreach ($cargos as $fila) {
            $id = $fila['id'] ?? null;

            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Cada cargo debe tener un id entero positivo.');
            }

            if (isset($idsVistos[$id])) {
                throw new InvalidArgumentException("El cargo {$id} está duplicado en las filas recibidas.");
            }
            $idsVistos[$id] = true;

            if ($id === $cargoId) {
                $elegidoPresente = true;
            }

            $deuda = $this->calcularDeuda($fila, $id);

            if ($deuda === 0) {
                continue;
            }

            $this->validarPadreDeudor($fila, $id);
            $deudores[$id] = [
                'deuda' => $deuda,
                'timestamp' => $this->resolverVigencia($fila, $id),
            ];
        }

        if (! $elegidoPresente) {
            throw new InvalidArgumentException("El cargo elegido {$cargoId} no está presente en las filas recibidas.");
        }

        return $deudores;
    }

    /**
     * Deuda pendiente del cargo; cero significa saldado.
     *
     * @param  array<string, mixed>  $fila
     */
    public function calcularDeuda(array $fila, int $id): int
    {
        $precio = $fila['precio_original'] ?? null;

        if (! is_int($precio) || $precio < 1 || $precio > self::IMPORTE_MAXIMO) {
            throw new RuntimeException("El cargo {$id} tiene un precio_original inválido para distribuir crédito.");
        }

        $aplicado = $fila['aplicado_ars'] ?? null;

        if (! is_int($aplicado) || $aplicado < 0 || $aplicado > $precio) {
            throw new RuntimeException("El cargo {$id} tiene un aplicado_ars inválido para distribuir crédito.");
        }

        $eximido = array_key_exists('eximido_ars', $fila) ? $fila['eximido_ars'] : 0;
        if (! is_int($eximido) || $eximido < 0 || $eximido > self::IMPORTE_MAXIMO || $eximido > $precio - $aplicado) {
            throw new RuntimeException("El cargo {$id} tiene un eximido_ars inválido o supera la deuda pendiente.");
        }

        return $precio - $aplicado - $eximido;
    }

    /**
     * Un deudor exige un cobro padre válido para recibir crédito del plan.
     *
     * @param  array<string, mixed>  $fila
     */
    private function validarPadreDeudor(array $fila, int $id): void
    {
        if (($fila['padre_valido'] ?? null) !== true) {
            throw new RuntimeException("El cargo deudor {$id} exige un cobro padre válido para distribuir crédito.");
        }
    }

    /**
     * Timestamp de la fecha vigente del deudor, o null cuando no tiene fecha
     * (ausente o en blanco). Falla cerrado ante fechas ilegibles: nunca usa
     * snapshot ni created_at como respaldo.
     *
     * @param  array<string, mixed>  $fila
     */
    private function resolverVigencia(array $fila, int $id): ?int
    {
        $fecha = $fila['fecha_vigente'] ?? null;

        if ($fecha === null) {
            return null;
        }

        if (! is_string($fecha)) {
            throw new RuntimeException("El cargo {$id} tiene una fecha_vigente con tipo ilegal.");
        }

        if (trim($fecha) === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->getTimestamp();
        } catch (Throwable $error) {
            throw new RuntimeException("El cargo {$id} tiene una fecha_vigente ilegible para ordenar el plan.");
        }
    }

    /**
     * Orden de negocio: elegido primero (aun sin fecha o más futuro), resto por
     * fecha ascendente con id ascendente de desempate y sin fecha al final.
     *
     * @param  array<int, array{deuda: int, timestamp: ?int}>  $deudores
     * @return list<int>
     */
    private function ordenarDeudores(array $deudores, int $cargoId): array
    {
        $resto = $deudores;
        unset($resto[$cargoId]);

        $ids = array_keys($resto);
        usort($ids, fn (int $a, int $b): int => $this->compararDeudores($a, $b, $resto));

        if (isset($deudores[$cargoId])) {
            array_unshift($ids, $cargoId);
        }

        return array_values($ids);
    }

    /**
     * @param  array<int, array{deuda: int, timestamp: ?int}>  $deudores
     */
    private function compararDeudores(int $a, int $b, array $deudores): int
    {
        $fechaA = $deudores[$a]['timestamp'];
        $fechaB = $deudores[$b]['timestamp'];

        if ($fechaA === null && $fechaB === null) {
            return $a <=> $b;
        }

        if ($fechaA === null) {
            return 1;
        }

        if ($fechaB === null) {
            return -1;
        }

        return ($fechaA <=> $fechaB) ?: ($a <=> $b);
    }
}
