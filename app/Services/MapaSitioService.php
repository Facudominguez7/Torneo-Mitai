<?php

namespace App\Services;

use App\Models\Edicion;

/**
 * Arma las URLs públicas del sitemap: cada página una vez y, las que dependen de la edición,
 * también una vez por edición, porque el selector de edición no es un enlace que Google pueda seguir.
 */
class MapaSitioService
{
    /** @return list<string> */
    public function urls(): array
    {
        $paginas = config('seo.paginas');
        $urls = array_map(fn (string $ruta): string => route($ruta), array_keys($paginas));

        $porEdicion = array_keys(array_filter($paginas, fn (array $pagina): bool => $pagina['por_edicion'] ?? false));
        $ediciones = Edicion::query()->orderBy('id')->pluck('id');

        foreach ($ediciones as $idEdicion) {
            foreach ($porEdicion as $ruta) {
                $urls[] = route($ruta, ['idEdicion' => $idEdicion]);
            }
        }

        return $urls;
    }
}
