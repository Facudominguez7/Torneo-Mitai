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

    // El plan gratuito de Resend permite 100 correos individuales por día: estos topes cuidan ese cupo.
    'minutos_entre_confirmaciones' => 10,
    'pedidos_por_dia' => 20,

    'max_equipos' => 5,

    // Contactos de Resend. Con MailHog en local no se sincroniza, así no se crean contactos reales.
    // Los tópicos se crearon con suscripción por defecto opt_out: solo recibe quien eligió ese aviso.
    'resend' => [
        'sincronizar' => (bool) env('SUSCRIPCIONES_SINCRONIZAR', env('SUSCRIPCIONES_MAILER', 'resend') === 'resend'),
        'segmento' => env('RESEND_SEGMENTO_SUSCRIPTORES', '93b53fc4-4e79-4f56-9813-13a0b94490e6'),
        'topicos' => [
            'resumen_fecha' => env('RESEND_TOPICO_RESUMEN_FECHA', '3e432e06-de48-468c-951f-9f0164d1aa29'),
            'avisos' => env('RESEND_TOPICO_AVISOS', '353f4888-0741-48c1-adcc-b9fc3f576185'),
            'proxima_fecha' => env('RESEND_TOPICO_PROXIMA_FECHA', '2afdf8cb-74b8-4463-9ff8-bed5297764af'),
        ],
        // Pausa entre suscriptores del comando de sincronización (Resend admite 10 pedidos por segundo).
        'pausa_ms' => 300,
    ],

    // Avisos que se pueden elegir y su columna en suscriptores.
    'avisos' => [
        'resumen_fecha' => 'recibe_resumen_fecha',
        'avisos' => 'recibe_avisos',
        'proxima_fecha' => 'recibe_proxima_fecha',
    ],
];
