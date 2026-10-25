<?php

namespace App\View\Composers;

use App\Models\Edicion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Arma los metadatos del <head> (título, descripción, canónica, robots, Open Graph)
 * según la ruta y la edición que muestra la página.
 */
class SeoComposer
{
    public function __construct(private readonly Request $request) {}

    public function compose(View $view): void
    {
        if (array_key_exists('seo', $view->getData())) {
            return;
        }

        $configuracion = config('seo');
        $ruta = $this->request->route()?->getName();
        $pagina = $configuracion['paginas'][$ruta] ?? null;
        $edicion = $pagina ? $this->nombreEdicion($view) : null;

        $titulo = $pagina ? $this->reemplazarEdicion($pagina['titulo'], $edicion) : $configuracion['sitio'];
        $descripcion = $pagina ? $this->reemplazarEdicion($pagina['descripcion'], $edicion) : $configuracion['descripcion'];

        $view->with('seo', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'canonica' => $this->urlCanonica($configuracion['parametros_canonicos']),
            'indexar' => ! $this->request->is('Panel*') && ! ($ruta && Str::is($configuracion['sin_indexar'], $ruta)),
            'imagen' => asset($configuracion['imagen']),
            'sitio' => $configuracion['sitio'],
            'esInicio' => $ruta === 'home',
        ]);
    }

    private function nombreEdicion(View $view): ?string
    {
        $edicion = $view->getData()['EdicionSeleccionada'] ?? null;

        if (! $edicion instanceof Edicion) {
            $idEdicion = $this->request->query('idEdicion');
            $edicion = $idEdicion ? Edicion::query()->find($idEdicion) : null;
            $edicion ??= Edicion::query()->latest('id')->first();
        }

        return $edicion?->nombre;
    }

    private function reemplazarEdicion(string $texto, ?string $edicion): string
    {
        return str_replace(':edicion', $edicion ?? config('seo.sitio'), $texto);
    }

    /** @param  list<string>  $parametros */
    private function urlCanonica(array $parametros): string
    {
        $conservados = array_filter(
            array_intersect_key($this->request->query(), array_flip($parametros)),
            fn (mixed $valor): bool => is_scalar($valor) && $valor !== '',
        );
        uksort($conservados, fn (string $a, string $b): int => array_search($a, $parametros) <=> array_search($b, $parametros));

        return $conservados ? $this->request->url().'?'.http_build_query($conservados) : $this->request->url();
    }
}
