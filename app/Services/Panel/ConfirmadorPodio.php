<?php

namespace App\Services\Panel;

use App\Models\Campeon;
use App\Models\Copa;
use App\Models\Subcampeon;
use Illuminate\Support\Facades\DB;

/**
 * Confirma el podio sugerido de una copa (T8b, panel-admin-redesign).
 *
 * Está separado de SugerenciaPodio (que documenta que nunca guarda nada)
 * porque acá sí se persiste. Serializa confirmaciones concurrentes de la
 * misma copa (doble click, dos admins a la vez) tomando un lock de fila
 * dentro de la transacción: sin eso, dos confirmaciones simultáneas pueden
 * pasar el chequeo de "¿ya existe?" antes de que cualquiera de las dos
 * inserte, porque campeones/subcampeones no tienen restricción unique (no
 * se puede agregar sin migrar el esquema). El chequeo de existencia y la
 * sugerencia se vuelven a calcular server-side ya con el lock tomado, para
 * no confiar en lo que ControladorPodio::confirmar calculó antes de entrar
 * a la transacción.
 */
class ConfirmadorPodio
{
    public function __construct(private readonly SugerenciaPodio $sugerenciaPodio) {}

    /**
     * @return array{resultado: 'confirmado'|'ya_existe'|'sin_sugerencia'}
     */
    public function confirmar(int $idEdicion, int $idCategoria, int $idCopa): array
    {
        $resultado = 'sin_sugerencia';

        DB::transaction(function () use ($idEdicion, $idCategoria, $idCopa, &$resultado): void {
            // La copa siempre existe (ConfirmarRequest valida "exists:copas,id"):
            // sirve como fila para serializar sin tener que tocar el esquema.
            Copa::whereKey($idCopa)->lockForUpdate()->first();

            $yaExiste = Campeon::where('idEdicion', $idEdicion)
                ->where('idCategoria', $idCategoria)
                ->where('idCopa', $idCopa)
                ->exists()
                || Subcampeon::where('idEdicion', $idEdicion)
                    ->where('idCategoria', $idCategoria)
                    ->where('idCopa', $idCopa)
                    ->exists();

            if ($yaExiste) {
                $resultado = 'ya_existe';

                return;
            }

            $sugerencia = $this->sugerenciaPodio->sugerenciaParaCopa($idEdicion, $idCategoria, $idCopa);

            if (! $sugerencia) {
                $resultado = 'sin_sugerencia';

                return;
            }

            Campeon::create([
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idCopa' => $idCopa,
                'idEquipo' => $sugerencia['idEquipoCampeon'],
            ]);

            Subcampeon::create([
                'idEdicion' => $idEdicion,
                'idCategoria' => $idCategoria,
                'idCopa' => $idCopa,
                'idEquipo' => $sugerencia['idEquipoSubcampeon'],
            ]);

            $resultado = 'confirmado';
        });

        return ['resultado' => $resultado];
    }
}
