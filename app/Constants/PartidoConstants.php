<?php

namespace App\Constants;

use App\Models\Partido;
use App\Models\InstanciaFinal;

class PartidoConstants
{
    // Tipos de partido como strings
    const TIPO_PARTIDO_NORMAL = 'partido';
    const TIPO_INSTANCIA_FINAL = 'instancia_final';
    
    // Clases de modelos polimórficos
    const CLASS_PARTIDO = Partido::class;
    const CLASS_INSTANCIA_FINAL = InstanciaFinal::class;
    
    /**
     * Obtener la clase del modelo según el tipo de partido
     * 
     * @param string $tipo
     * @return string
     */
    public static function getModelClass(string $tipo): string
    {
        $tipoNormalizado = strtolower(str_replace(['-', ' '], '_', trim($tipo)));

        // Compatibilidad con variantes legacy usadas en rutas y formularios.
        if (in_array($tipoNormalizado, [self::TIPO_INSTANCIA_FINAL, 'instanciafinal'], true)) {
            return self::CLASS_INSTANCIA_FINAL;
        }

        return self::CLASS_PARTIDO;
    }
    
    /**
     * Determinar si el tipo es instancia final
     * 
     * @param string $tipo
     * @return bool
     */
    public static function isInstanciaFinal(string $tipo): bool
    {
        $tipoNormalizado = strtolower(str_replace(['-', ' '], '_', trim($tipo)));

        return in_array($tipoNormalizado, [self::TIPO_INSTANCIA_FINAL, 'instanciafinal'], true);
    }
}
