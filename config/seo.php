<?php

/*
 * Metadatos del sitio público para buscadores y redes (SeoComposer arma el <head> con esto).
 * ":edicion" se reemplaza por el nombre de la edición que muestra la página.
 */
return [
    'sitio' => "Torneos Mita'í",

    'descripcion' => "Fixture, resultados, tablas de posiciones y goleadores de Torneos Mita'í, torneo de fútbol infantil en Posadas, Misiones.",

    'imagen' => 'og-torneos-mitai.jpg',

    'ubicacion' => [
        'localidad' => 'Posadas',
        'region' => 'Misiones',
        'pais' => 'AR',
    ],

    'redes' => [
        'https://www.instagram.com/torneos.mitaii/',
        'https://www.facebook.com/ligamitaijb',
    ],

    // Parámetros que cambian el contenido y se conservan en la URL canónica; el resto (orden, filtros) se descarta.
    'parametros_canonicos' => ['idEdicion', 'idCategoria', 'idGrupo', 'tipo'],

    // Rutas que no deben aparecer en los buscadores.
    'sin_indexar' => [
        'login', 'register', 'password.*', 'verification.*', 'profile.*', 'dashboard',
        'planillas-publicas.*', 'equipo-seguido',
    ],

    'paginas' => [
        'home' => [
            'titulo' => ':edicion · Fixture, resultados y tablas',
            'descripcion' => 'Fixture, resultados, tablas de posiciones y goleadores de la :edicion, torneo de fútbol infantil en Posadas, Misiones.',
        ],
        'fixture' => [
            'titulo' => 'Fixture y resultados · :edicion',
            'descripcion' => 'Fixture, horarios y resultados de cada fecha de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'tabla-posiciones' => [
            'titulo' => 'Tabla de posiciones · :edicion',
            'descripcion' => 'Tabla de posiciones por categoría y grupo de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'tabla-goleadores' => [
            'titulo' => 'Tabla de goleadores · :edicion',
            'descripcion' => 'Los goleadores de cada categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'tabla-vallas' => [
            'titulo' => 'Vallas menos vencidas · :edicion',
            'descripcion' => 'Las vallas menos vencidas de cada categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'goleadores' => [
            'titulo' => 'Goleadores · :edicion',
            'descripcion' => 'Los goleadores destacados de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'vallas' => [
            'titulo' => 'Vallas · :edicion',
            'descripcion' => 'Las vallas destacadas de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'campeones' => [
            'titulo' => 'Campeones · :edicion',
            'descripcion' => 'Los campeones de cada copa y categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'subcampeones' => [
            'titulo' => 'Subcampeones · :edicion',
            'descripcion' => 'Los subcampeones de cada copa y categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'instancias-finales' => [
            'titulo' => 'Instancias finales · :edicion',
            'descripcion' => 'Cruces y resultados de las instancias finales de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'terminos' => [
            'titulo' => "Términos y condiciones · Torneos Mita'í",
            'descripcion' => "Condiciones de uso del sitio de Torneos Mita'í: cuentas, información del torneo y panel de administración.",
        ],
        'privacidad' => [
            'titulo' => "Política de privacidad · Torneos Mita'í",
            'descripcion' => "Qué datos guarda el sitio de Torneos Mita'í, para qué se usan y cómo pedir su acceso o eliminación.",
        ],
    ],
];
