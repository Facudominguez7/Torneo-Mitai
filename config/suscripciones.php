<?php

/*
 * Suscripciones a los avisos por correo (ver App\Services\SuscripcionService).
 */
return [
    // Mailer de los correos a suscriptores; los de la cuenta (código, contraseña) usan el mailer por defecto.
    'mailer' => env('SUSCRIPCIONES_MAILER', 'resend'),

    // Remitente: tiene que ser del dominio verificado en Resend.
    'remitente' => env('SUSCRIPCIONES_REMITENTE', 'avisos@notificaciones.torneosmitai.com'),

    // Horas de validez del enlace de confirmación.
    'horas_confirmacion' => 72,

    'max_equipos' => 5,

    // Avisos que se pueden elegir y su columna en suscriptores.
    'avisos' => [
        'resumen_fecha' => 'recibe_resumen_fecha',
        'avisos' => 'recibe_avisos',
        'proxima_fecha' => 'recibe_proxima_fecha',
    ],
];
