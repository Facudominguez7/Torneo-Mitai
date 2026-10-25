<?php

namespace App\Http\Controllers;

use App\Services\MapaSitioService;
use Illuminate\Http\Response;

class ControladorSeo extends Controller
{
    public function robots(): Response
    {
        $contenido = "User-agent: *\nDisallow: /Panel\n\nSitemap: ".route('sitemap')."\n";

        return response($contenido)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(MapaSitioService $mapaSitio): Response
    {
        // La declaración XML va fuera de la vista: Blade toma "<?xml" como apertura de PHP.
        $contenido = '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('seo.sitemap', ['urls' => $mapaSitio->urls()])->render();

        return response($contenido)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
