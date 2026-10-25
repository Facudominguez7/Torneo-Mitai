<?php

/*
 * Metadatos del sitio público para buscadores y redes (SeoComposer arma el <head> con esto).
 * ":edicion" se reemplaza por el nombre de la edición que muestra la página.
 */
return [
    'sitio' => "Torneos Mita'í",

    'descripcion' => "Fixture, resultados, tablas de posiciones y goleadores de Torneos Mita'í, torneo de fútbol infantil en Posadas, Misiones.",

    'imagen' => 'og-torneos-mitai.jpg',

    // Datos de la organización para el JSON-LD de la home (schema.org SportsOrganization).
    'organizacion' => [
        'logo' => 'fotos/Logo_Mitai_SinFondo.png',
        'deporte' => 'Fútbol',
        'resumen' => 'Torneo de fútbol infantil en Posadas, Misiones.',
    ],

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
        'planillas-publicas.*', 'equipo-seguido', 'email.*',
        'suscripcion.confirmar', 'suscripcion.preferencias*', 'suscripcion.baja*',
    ],

    /*
     * Cada página usa la edición que muestra. Sin edición elegida, las que dicen "ultima_por_defecto"
     * muestran la última; el resto solo muestra el selector y usa "descripcion_general".
     * Las de "por_edicion" se listan en el sitemap una vez por edición (?idEdicion=).
     */

    'paginas' => [
        'home' => [
            'ultima_por_defecto' => true,
            'titulo' => ':edicion · Fixture, resultados y tablas',
            'descripcion' => 'Fixture, resultados, tablas de posiciones y goleadores de la :edicion, torneo de fútbol infantil en Posadas, Misiones.',
        ],
        'fixture' => [
            'por_edicion' => true,
            'titulo' => 'Fixture y resultados · :edicion',
            'descripcion' => 'Fixture, horarios y resultados de cada fecha de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Fixture, horarios y resultados de cada fecha en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'tabla-posiciones' => [
            'por_edicion' => true,
            'titulo' => 'Tabla de posiciones · :edicion',
            'descripcion' => 'Tabla de posiciones por categoría y grupo de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Tablas de posiciones por categoría y grupo en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'tabla-goleadores' => [
            'por_edicion' => true,
            'titulo' => 'Tabla de goleadores · :edicion',
            'descripcion' => 'Los goleadores de cada categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Los goleadores de cada categoría en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'tabla-vallas' => [
            'por_edicion' => true,
            'titulo' => 'Vallas menos vencidas · :edicion',
            'descripcion' => 'Las vallas menos vencidas de cada categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Las vallas menos vencidas de cada categoría en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'goleadores' => [
            'por_edicion' => true,
            'titulo' => 'Goleadores · :edicion',
            'descripcion' => 'Los goleadores destacados de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Los goleadores destacados en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'vallas' => [
            'por_edicion' => true,
            'titulo' => 'Vallas · :edicion',
            'descripcion' => 'Las vallas destacadas de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Las vallas destacadas en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'campeones' => [
            'por_edicion' => true,
            'titulo' => 'Campeones · :edicion',
            'descripcion' => 'Los campeones de cada copa y categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Los campeones de cada copa y categoría en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'subcampeones' => [
            'por_edicion' => true,
            'titulo' => 'Subcampeones · :edicion',
            'descripcion' => 'Los subcampeones de cada copa y categoría de la :edicion, fútbol infantil en Posadas, Misiones.',
            'descripcion_general' => "Los subcampeones de cada copa y categoría en todas las ediciones de Torneos Mita'í, fútbol infantil en Posadas, Misiones.",
        ],
        'instancias-finales' => [
            'ultima_por_defecto' => true,
            'por_edicion' => true,
            'titulo' => 'Instancias finales · :edicion',
            'descripcion' => 'Cruces y resultados de las instancias finales de la :edicion, fútbol infantil en Posadas, Misiones.',
        ],
        'terminos' => [
            'titulo' => "Términos y condiciones · Torneos Mita'í",
            'descripcion' => "Condiciones de uso del sitio de Torneos Mita'í: cuentas, información del torneo y panel de administración.",
        ],
        'suscripcion.create' => [
            'titulo' => "Avisos por correo · Torneos Mita'í",
            'descripcion' => "Recibí por correo el resumen de cada fecha, la próxima fecha y los avisos de Torneos Mita'í.",
        ],
        'privacidad' => [
            'titulo' => "Política de privacidad · Torneos Mita'í",
            'descripcion' => "Qué datos guarda el sitio de Torneos Mita'í, para qué se usan y cómo pedir su acceso o eliminación.",
        ],
    ],
];
