<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ControladorSitemap extends Controller
{
    /**
     * Rutas públicas indexables que componen el sitemap.
     *
     * @var list<string>
     */
    private const RUTAS_PUBLICAS = [
        'home',
        'fixture',
        'campeones',
        'subcampeones',
        'goleadores',
        'vallas',
        'tabla-posiciones',
        'tabla-goleadores',
        'tabla-vallas',
        'instancias-finales',
    ];

    public function index(): Response
    {
        $urls = collect(self::RUTAS_PUBLICAS)
            ->map(fn (string $ruta): string => route($ruta));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
