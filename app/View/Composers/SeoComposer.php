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
        $edicion = $pagina ? $this->nombreEdicion($view, $pagina['ultima_por_defecto'] ?? false) : null;

        $titulo = $pagina ? str_replace(':edicion', $edicion ?? $configuracion['sitio'], $pagina['titulo']) : $configuracion['sitio'];
        $descripcion = match (true) {
            $pagina === null => $configuracion['descripcion'],
            $edicion !== null => str_replace(':edicion', $edicion, $pagina['descripcion']),
            default => $pagina['descripcion_general'] ?? $configuracion['descripcion'],
        };

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

    /** La edición que muestra la página: la elegida por el controlador o, si la página la usa por defecto, la última. */
    private function nombreEdicion(View $view, bool $ultimaPorDefecto): ?string
    {
        $edicion = $view->getData()['EdicionSeleccionada'] ?? null;

        if (! $edicion instanceof Edicion && $ultimaPorDefecto) {
            $edicion = Edicion::query()->latest('id')->first();
        }

        return $edicion instanceof Edicion ? $edicion->nombre : null;
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
