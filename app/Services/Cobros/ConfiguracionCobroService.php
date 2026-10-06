<?php

namespace App\Services\Cobros;

use App\Models\ConfiguracionCobroPlanilla;
use RuntimeException;

class ConfiguracionCobroService
{
    private const ID_GLOBAL = 1;

    private const ERROR_FILA_AUSENTE = 'La configuración global de cobros no está inicializada (falta la fila id 1).';

    public function obtenerConfiguracion(): ConfiguracionCobroPlanilla
    {
        return $this->obtenerFilaGlobal();
    }

    private function obtenerFilaGlobal(bool $bloqueada = false): ConfiguracionCobroPlanilla
    {
        $consulta = ConfiguracionCobroPlanilla::query()->whereKey(self::ID_GLOBAL);

        if ($bloqueada) {
            $consulta->lockForUpdate();
        }

        $configuracion = $consulta->first();

        if ($configuracion === null) {
            throw new RuntimeException(self::ERROR_FILA_AUSENTE);
        }

        return $configuracion;
    }
}
