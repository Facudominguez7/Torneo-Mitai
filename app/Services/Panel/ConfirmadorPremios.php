<?php

namespace App\Services\Panel;

use App\Models\Categoria;
use App\Models\EquipoEdicion;
use App\Models\Goleador;
use App\Models\TablaGoleador;
use App\Models\VallaMenosVencida;
use Illuminate\Support\Facades\DB;

/**
 * Guarda el goleador o la valla elegidos desde Premios individuales (T8c,
 * panel-admin-redesign). Está separado de SugerenciaPremios (que nunca
 * guarda nada) porque acá sí se persiste.
 *
 * Serializa altas concurrentes de la misma categoría (doble click, dos
 * admins a la vez) con un lock de fila sobre la categoría dentro de la
 * transacción: sin eso, dos altas simultáneas pueden pasar el chequeo de "¿ya
 * existe?" antes de que cualquiera de las dos inserte, porque goleadores y
 * vallas_menos_vencidas no tienen restricción unique. El chequeo de
 * existencia y el candidato elegido se vuelven a resolver server-side ya con
 * el lock tomado, para no confiar en nada que ControladorPremios haya
 * calculado antes de entrar a la transacción — y, sobre todo, para nunca
 * confiar en un nombre o equipo que mande el cliente para el goleador.
 *
 * Alcance: protege solo premios.goleador y premios.valla. El alta manual
 * (goleador.store, valla.store) sigue sin este chequeo, igual que antes.
 */
class ConfirmadorPremios
{
    /**
     * Crea el Goleador a partir de una fila de la tabla de goleadores: el
     * nombre y el equipo se resuelven acá, nunca a partir de lo que mande el
     * cliente.
     *
     * @return array{resultado: 'confirmado'|'ya_existe'|'dni_invalido'}
     */
    public function guardarGoleador(int $idEdicion, int $idCategoria, string $dniJugador): array
    {
        $resultado = 'dni_invalido';

        DB::transaction(function () use ($idEdicion, $idCategoria, $dniJugador, &$resultado): void {
            // La categoría siempre existe (el FormRequest valida "exists:categorias,id"):
            // sirve como fila para serializar sin tener que tocar el esquema.
            Categoria::whereKey($idCategoria)->lockForUpdate()->first();

            $yaExiste = Goleador::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->exists();

            if ($yaExiste) {
                $resultado = 'ya_existe';

                return;
            }

            $filaTabla = TablaGoleador::with('jugador')
                ->where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->where('dni_jugador', $dniJugador)
                ->first();

            if (! $filaTabla) {
                $resultado = 'dni_invalido';

                return;
            }

            Goleador::create([
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idEquipo' => $filaTabla->idEquipo,
                'nombre' => $filaTabla->nombre_completo,
            ]);

            $resultado = 'confirmado';
        });

        return ['resultado' => $resultado];
    }

    /**
     * Crea la VallaMenosVencida con el equipo verificado contra
     * EquipoEdicion (nunca confía en que el equipo mandado por el cliente
     * pertenezca a esta edición y categoría) y el nombre del arquero que
     * cargó el admin.
     *
     * @return array{resultado: 'confirmado'|'ya_existe'|'equipo_invalido'}
     */
    public function guardarValla(int $idEdicion, int $idCategoria, int $idEquipo, string $nombreArquero): array
    {
        $resultado = 'equipo_invalido';

        DB::transaction(function () use ($idEdicion, $idCategoria, $idEquipo, $nombreArquero, &$resultado): void {
            Categoria::whereKey($idCategoria)->lockForUpdate()->first();

            $yaExiste = VallaMenosVencida::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->exists();

            if ($yaExiste) {
                $resultado = 'ya_existe';

                return;
            }

            $equipoValido = EquipoEdicion::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->where('idEquipo', $idEquipo)
                ->exists();

            if (! $equipoValido) {
                $resultado = 'equipo_invalido';

                return;
            }

            VallaMenosVencida::create([
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idEquipo' => $idEquipo,
                'nombre' => $nombreArquero,
            ]);

            $resultado = 'confirmado';
        });

        return ['resultado' => $resultado];
    }
}
