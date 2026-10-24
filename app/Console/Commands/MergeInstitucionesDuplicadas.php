<?php

namespace App\Console\Commands;

use App\Models\Equipo;
use App\Models\Institucion;
use App\Services\NormalizadorInstituciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeInstitucionesDuplicadas extends Command
{
    protected $signature = 'instituciones:merge-duplicates {--dry-run : Solo muestra lo que haría}';

    protected $description = 'Detecta instituciones equivalentes por nombre normalizado y fusiona sus equipos en una sola.';

    public function handle(): int
    {
        $this->info('Analizando instituciones...');
        $inst = Institucion::all();

        // Agrupar por nombre normalizado
        $grupos = [];
        foreach ($inst as $i) {
            $base = NormalizadorInstituciones::estandarizar($i->nombre);
            $grupos[$base][] = $i;
        }

        $dry = $this->option('dry-run');
        $fusiones = 0;

        DB::beginTransaction();
        try {
            foreach ($grupos as $base => $lista) {
                if (count($lista) < 2) {
                    continue;
                } // No hay duplicados

                // Ordenar por id asc y conservar la primera como principal
                usort($lista, fn ($a, $b) => $a->id <=> $b->id);
                $principal = array_shift($lista);
                $idsDuplicados = array_map(fn ($x) => $x->id, $lista);

                $this->line("Grupo '{$base}' -> principal: {$principal->id} ({$principal->nombre}) duplicados: ".implode(',', $idsDuplicados));

                if ($dry) {
                    continue; // Solo mostrar
                }

                // Reasignar equipos a la institución principal
                Equipo::whereIn('idInstitucion', $idsDuplicados)->update(['idInstitucion' => $principal->id]);
                // Eliminar instituciones duplicadas
                Institucion::whereIn('id', $idsDuplicados)->delete();
                $fusiones++;
            }

            if ($dry) {
                DB::rollBack();
                $this->comment('Dry-run: no se realizaron cambios.');
            } else {
                DB::commit();
                $this->info("Fusiones realizadas: {$fusiones}");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error durante la fusión: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
