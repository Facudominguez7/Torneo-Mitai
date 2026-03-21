<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Edicion;
use App\Models\Equipo;
use App\Models\ListaBuenaFe;
use App\Models\PlanillaJugador;

/**
 * Servicio de Validación de Reglas de Negocio para Jugadores
 *
 * Centraliza todas las validaciones de reglas para agregar jugadores a equipos,
 * evitando duplicación de código entre ControladorListaBuenaFe y ControladorPlanillaJugador.
 */
class ValidadorReglasJugador
{
    /**
     * Validar todas las reglas de negocio para agregar un jugador a un equipo
     *
     * Retorna null si todas las validaciones pasan, o un array con el tipo de error
     * y mensaje si alguna validación falla.
     *
     * Tipos de retorno:
     * - null: Todas las validaciones pasaron
     * - ['type' => 'error', 'message' => '...']: Error bloqueante (HARD)
     * - ['type' => 'warning', 'message' => '...']: Advertencia administrativa (SOFT)
     *
     * @param  string  $dni_jugador  DNI del jugador
     * @param  int  $idEquipo  ID del equipo destino
     * @param  int  $idEdicion  ID de la edición
     * @param  int  $idCategoria  ID de la categoría
     * @param  string  $fecha_nacimiento  Fecha de nacimiento del jugador
     * @return array|null Null si OK, array con tipo y mensaje si falla
     */
    public function validarJugador($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento, $esReactivacion = false)
    {
        // 1. Validar si ya jugó en otro equipo de la misma categoría y edición (Regla de Planilla)
        $resultado = $this->validarEquipoUnicoPorCategoria($dni_jugador, $idEquipo, $idEdicion, $idCategoria);
        if ($resultado) {
            return $resultado;
        }

        // 2. Validar si está activo en la Lista de Buena Fe de otro equipo
        $resultado = $this->validarListaBuenaFeOtroEquipo($dni_jugador, $idEquipo, $idEdicion, $idCategoria);
        if ($resultado) {
            return $resultado;
        }

        // 3. Validar si jugador existe como inactivo (advertir para reactivar)
        // Solo si NO es una reactivación (si es reactivación, ya sabemos que está inactivo)
        if (! $esReactivacion) {
            $resultado = $this->validarJugadorInactivo($dni_jugador, $idEquipo, $idEdicion);
            if ($resultado) {
                return $resultado;
            }
        }

        // 4. Validaciones de Categoría y Edad
        $resultado = $this->validarCategoriasYEdad($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento);
        if ($resultado) {
            return $resultado;
        }

        // 5. Validar límite configurable de jugadores activos por edición (WARNING, no bloquea)
        $resultado = $this->validarLimiteJugadores($idEquipo, $idEdicion);
        if ($resultado) {
            return $resultado;
        }

        // 6. Verificar si ya está en la lista de buena fe (activo)
        $resultado = $this->validarDuplicadoEnLista($dni_jugador, $idEquipo, $idEdicion);
        if ($resultado) {
            return $resultado;
        }

        return null; // Todas las validaciones pasaron
    }

    /**
     * Validar reglas de negocio al editar un registro ya existente en Lista de Buena Fe.
     *
     * Excluye el registro actual para permitir correcciones del mismo jugador,
     * incluso cuando se modifica el DNI.
     */
    public function validarActualizacionJugador($idListaBuenaFe, $dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento)
    {
        $resultado = $this->validarEquipoUnicoPorCategoria($dni_jugador, $idEquipo, $idEdicion, $idCategoria);
        if ($resultado) {
            return $resultado;
        }

        $resultado = $this->validarListaBuenaFeOtroEquipo($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $idListaBuenaFe);
        if ($resultado) {
            return $resultado;
        }

        $resultado = $this->validarRegistroExistenteEnLista($dni_jugador, $idEquipo, $idEdicion, $idListaBuenaFe);
        if ($resultado) {
            return $resultado;
        }

        return $this->validarCategoriasYEdad($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento);
    }

    /**
     * Validar exclusivamente reglas de categoría y edad.
     *
     * Este método se usa en flujos de edición de datos donde no corresponde
     * ejecutar validaciones de duplicidad/equipo (por ejemplo, update de
     * fecha de nacimiento en Lista de Buena Fe).
     *
     * @param  string  $dni_jugador  DNI del jugador
     * @param  int  $idEquipo  ID del equipo destino
     * @param  int  $idEdicion  ID de la edición
     * @param  int  $idCategoria  ID de la categoría
     * @param  string  $fecha_nacimiento  Fecha de nacimiento del jugador
     * @return array|null Null si OK, array con tipo y mensaje si falla
     */
    public function validarSoloCategoriaYEdad($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento)
    {
        return $this->validarCategoriasYEdad($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento);
    }

    /**
     * Validar si el jugador ya jugó para otro equipo en la misma categoría y edición
     */
    private function validarEquipoUnicoPorCategoria($dni_jugador, $idEquipo, $idEdicion, $idCategoria)
    {
        $jugadorEnOtroEquipo = PlanillaJugador::where('dni_jugador', $dni_jugador)
            ->where('idEquipo', '!=', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->first();

        if ($jugadorEnOtroEquipo) {
            $equipoAsignado = Equipo::find($jugadorEnOtroEquipo->idEquipo);

            return [
                'type' => 'warning',
                'message' => 'El jugador ya jugó para el equipo '.($equipoAsignado->nombre ?? 'Desconocido').' en esta categoría y edición.',
            ];
        }

        return null;
    }

    /**
     * Validar si está activo en la Lista de Buena Fe de otro equipo
     */
    private function validarListaBuenaFeOtroEquipo($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $idRegistroExcluir = null)
    {
        $query = ListaBuenaFe::where('dni_jugador', $dni_jugador)
            ->where('idEquipo', '!=', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('idCategoria', $idCategoria)
            ->where('activo', true);

        if ($idRegistroExcluir) {
            $query->where('id', '!=', $idRegistroExcluir);
        }

        $enOtraLista = $query->first();

        if ($enOtraLista) {
            $equipoOtro = Equipo::find($enOtraLista->idEquipo);

            return [
                'type' => 'warning',
                'message' => 'El jugador ya está en la Lista de Buena Fe del equipo '.($equipoOtro->nombre ?? 'Desconocido').'.',
            ];
        }

        return null;
    }

    /**
     * Validar si el jugador existe como inactivo en el mismo equipo
     * (Advertencia administrativa para registrar observación)
     */
    private function validarJugadorInactivo($dni_jugador, $idEquipo, $idEdicion, $idRegistroExcluir = null)
    {
        $query = ListaBuenaFe::where('dni_jugador', $dni_jugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('activo', false);

        if ($idRegistroExcluir) {
            $query->where('id', '!=', $idRegistroExcluir);
        }

        $jugadorInactivo = $query->first();

        if ($jugadorInactivo) {
            return [
                'type' => 'warning',
                'message' => 'El jugador ya existe en la lista pero está INACTIVO (dado de baja). ¿Desea reactivarlo en lugar de crear un nuevo registro?',
            ];
        }

        return null;
    }

    /**
     * Validar reglas de categorías y edad.
     *
     * Reglas clave:
     * - Permite subir de una categoria menor a una de mayor edad (ej: 2014 -> 2013).
     * - Bloquea bajar de una categoria de mayor edad a una menor (ej: 2013 -> 2014).
     * - Mantiene advertencias administrativas para casos especiales (institución).
     */
    private function validarCategoriasYEdad($dni_jugador, $idEquipo, $idEdicion, $idCategoria, $fecha_nacimiento)
    {
        // Helper para extraer año de categoría
        $extraerAnioCategoria = function (string $nombre) {
            if (preg_match('/(20\d{2})/', $nombre, $m)) {
                return (int) $m[1];
            }

            return null;
        };

        // Calcular edad del jugador
        $anioActual = (int) date('Y');
        $anioNacimiento = (int) \Carbon\Carbon::parse($fecha_nacimiento)->year;
        $edadJugador = $anioActual - $anioNacimiento;

        $categoriaEquipoActual = Categoria::find($idCategoria);
        if (! $categoriaEquipoActual) {
            return [
                'type' => 'error',
                'message' => 'No se pudo validar la categoría del equipo destino.',
            ];
        }

        $anioEquipoActual = $extraerAnioCategoria($categoriaEquipoActual->nombreCategoria);

        // Categorías por año (ej: 2015, 2016)
        if (! is_null($anioEquipoActual)) {
            // Bloquea SOLO si es más grande que la categoría permitida.
            // Ejemplo: categoría 2015 -> no puede un 2014 (más grande).
            if ($anioNacimiento < $anioEquipoActual) {
                return [
                    'type' => 'error',
                    'message' => 'La fecha de nacimiento del jugador ('.$anioNacimiento.') no corresponde a "'.$categoriaEquipoActual->nombreCategoria.'". El jugador es mayor que la edad permitida.',
                ];
            }
        } else {
            // Categorías especiales (ej: Femenino Sub 13)
            if (preg_match('/Sub\s*(\d{1,2})/i', $categoriaEquipoActual->nombreCategoria, $mSub)) {
                $limite = (int) $mSub[1];
                if ($edadJugador > $limite) {
                    return [
                        'type' => 'error',
                        'message' => 'El jugador excede la edad límite para '.$categoriaEquipoActual->nombreCategoria.'.',
                    ];
                }
            }
        }

        // Obtener historial de categorías del jugador
        $categoriasJugador = PlanillaJugador::join('equipo_ediciones', function ($join) {
            $join->on('planilla_jugadores.idEquipo', '=', 'equipo_ediciones.idEquipo')
                ->on('planilla_jugadores.idEdicion', '=', 'equipo_ediciones.idEdicion');
        })
            ->join('categorias', 'equipo_ediciones.idCategoria', '=', 'categorias.id')
            ->join('equipos', 'equipo_ediciones.idEquipo', '=', 'equipos.id')
            ->where('planilla_jugadores.dni_jugador', $dni_jugador)
            ->where('planilla_jugadores.idEdicion', $idEdicion)
            ->select('categorias.nombreCategoria', 'equipos.nombre as nombreEquipo', 'equipo_ediciones.idEquipo as idEquipoCategoria', 'equipos.idInstitucion')
            ->distinct()
            ->get();

        $equipoDestino = Equipo::find($idEquipo);
        $institucionDestinoId = $equipoDestino?->idInstitucion;

        foreach ($categoriasJugador as $categoriaJugador) {
            $anioCategoriaJugador = $extraerAnioCategoria($categoriaJugador->nombreCategoria);
            $institucionOrigenId = $categoriaJugador->idInstitucion;

            if (is_null($anioCategoriaJugador) || is_null($anioEquipoActual)) {
                continue;
            }

            // Regla administrativa: subir de una categoria menor a una de mayor edad,
            // solo dentro de la misma institucion.
            // Se informa como WARNING para registro conflictivo, sin bloquear por edad.
            $mismaInstitucion = $institucionDestinoId && $institucionDestinoId === $institucionOrigenId;
            if ($anioCategoriaJugador > $anioEquipoActual && ! $mismaInstitucion) {
                return [
                    'type' => 'warning',
                    'message' => 'El jugador ya pertenece al equipo "'.$categoriaJugador->nombreEquipo.'" en la "'.$categoriaJugador->nombreCategoria.'". Solo puede subir a una categoria de mayor edad como "'.$categoriaEquipoActual->nombreCategoria.'" dentro de la misma institucion.',
                ];
            }
        }

        return null;
    }

    /**
     * Validar límite configurable de jugadores activos por edición.
     *
     * El límite también define la cantidad objetivo de filas en la planilla,
     * por lo que se centraliza aquí para mantener la misma regla operativa.
     * Retorna WARNING (no bloquea, solo advierte)
     */
    private function validarLimiteJugadores($idEquipo, $idEdicion)
    {
        $limiteJugadores = $this->obtenerLimiteJugadoresPorEdicion($idEdicion);

        $cantidadJugadores = ListaBuenaFe::where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('activo', true)
            ->count();

        if ($cantidadJugadores >= $limiteJugadores) {
            return [
                'type' => 'warning',
                'message' => 'El equipo ya tiene '.$cantidadJugadores.' jugadores activos. El límite recomendado para esta edición es '.$limiteJugadores.' jugadores por equipo.',
            ];
        }

        return null;
    }

    /**
     * Obtiene el límite de jugadores configurado para la edición.
     *
     * Si no existe configuración válida, utiliza 18 como valor seguro
     * para no romper el flujo de altas en entornos legacy.
     */
    private function obtenerLimiteJugadoresPorEdicion($idEdicion)
    {
        $edicion = Edicion::find($idEdicion);
        $limite = (int) ($edicion->jugadores_por_planilla ?? 18);

        return $limite > 0 ? $limite : 18;
    }

    /**
     * Verificar si ya está en la lista de buena fe (activo)
     */
    private function validarDuplicadoEnLista($dni_jugador, $idEquipo, $idEdicion, $idRegistroExcluir = null)
    {
        $query = ListaBuenaFe::where('dni_jugador', $dni_jugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('activo', true);

        if ($idRegistroExcluir) {
            $query->where('id', '!=', $idRegistroExcluir);
        }

        $existe = $query->exists();

        if ($existe) {
            return [
                'type' => 'error',
                'message' => 'El jugador ya está en la Lista de Buena Fe de este equipo.',
            ];
        }

        return null;
    }

    /**
     * Verifica si ya existe cualquier otro registro del mismo DNI en la misma
     * inscripción de Lista de Buena Fe, incluyendo activos e inactivos.
     */
    private function validarRegistroExistenteEnLista($dni_jugador, $idEquipo, $idEdicion, $idRegistroExcluir)
    {
        $existeOtroRegistro = ListaBuenaFe::where('dni_jugador', $dni_jugador)
            ->where('idEquipo', $idEquipo)
            ->where('idEdicion', $idEdicion)
            ->where('id', '!=', $idRegistroExcluir)
            ->exists();

        if ($existeOtroRegistro) {
            return [
                'type' => 'error',
                'message' => 'Ya existe otro registro en Lista de Buena Fe para ese DNI dentro del mismo equipo y edición.',
            ];
        }

        return null;
    }
}
