<?php

namespace App\Services;

class NormalizadorInstituciones
{
    /**
     * Estandariza un nombre institucional a una forma base.
     */
    public static function estandarizar(string $nombre): string
    {
        $original = $nombre;
        $n = mb_strtolower($nombre, 'UTF-8');

        // Reemplazar acentos
        $n = strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        // Quitar comillas, puntos, comas
        $n = preg_replace('/["\'\.,]/u', ' ', $n);

        // Expandir o normalizar abreviaturas comunes
        // Reglas de abreviaturas (usar regex para evitar colisiones dentro de palabras)
        $reemplazosRegex = [
            '/\bdep\.?\b/' => 'deportivo',
            '/\bdeport\b/' => 'deportivo',
            '/\bclub atletico\b/' => 'ca',
            '/\bc\.a\.\b/' => 'ca',
            '/\bc a\b/' => 'ca',
            // A.F. (solo cuando es sigla completa aislada, no cuando empieza 'franco')
            '/\ba\.f\.?\b/' => 'af',
            '/\ba f\b/' => 'af',
            // Academia Napoles variantes
            '/\ba\. napoles\b/' => 'academia napoles',
            '/\ba napoles\b/' => 'academia napoles',
        ];
        foreach ($reemplazosRegex as $pat => $rep) {
            $n = preg_replace($pat, $rep, $n);
        }

        // Eliminar sufijos de subdivisiones (colores o letras)
        $colores = ['blanco', 'negro', 'rojo', 'azul', 'verde', 'amarillo', 'naranja', 'celeste', 'gris', 'bordo', 'violeta'];
        foreach ($colores as $color) {
            $n = preg_replace('/\s'.$color.'$/', '', $n);
        }
        // Letra final aislada
        $n = preg_replace('/\s[a-z]$/', '', $n);

        // Contraer espacios
        $n = preg_replace('/\s{2,}/', ' ', $n);
        $n = trim($n);

        // Quitar palabra 'club' inicial redundante
        $n = preg_replace('/^club\s+/', '', $n);

        // Unificar cualquier variante que contenga 'guarani' a la forma corta 'guarani af'
        if (str_contains($n, 'guarani')) {
            $n = 'guarani af';
        }

        // Normalización final específica: Academia Nápoles
        if (str_contains($n, 'academia napoles')) {
            $n = 'academia napoles';
        }

        // Centro de Formación RC (variantes sin 'de', mayúsculas, acentos, etc.)
        if (preg_match('/centro\s+(de\s+)?formacion\s+rc/', $n)) {
            $n = 'centro de formacion rc';
        }

        // Variantes de Brown (J. B. Brown, J G Brown, J. G. Brown, etc.) => j b brown
        if (preg_match('/brown$/', $n)) {
            // Si contiene iniciales j y alguna de b/g (por posibles confusiones de fuente) lo normalizamos
            if (preg_match('/\bj[\s\.]*[bg][\s\.]*brown$/', $n) || preg_match('/\bbrown$/', $n)) {
                $n = 'j b brown';
            }
        }

        // Estrellitas / Estrellitas Misioneras => estrellitas misioneras
        if (preg_match('/^estrellitas(\s+misioneras)?$/', $n)) {
            $n = 'estrellitas misioneras';
        }

        // Real F.C. / Real fc / Real F. C. => real fc
        if (preg_match('/^real\s*f\.?\s*c\.?$/', $n)) {
            $n = 'real fc';
        }

        return $n ?: trim($original);
    }
}
