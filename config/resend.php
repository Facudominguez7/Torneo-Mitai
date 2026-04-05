<?php

$webhookTolerance = env('RESEND_WEBHOOK_TOLERANCE');

return [
    'api_key' => env('RESEND_API_KEY') ?: env('RESEND_KEY'),

    'domain' => env('RESEND_DOMAIN'),

    'path' => env('RESEND_PATH', 'resend'),

    'webhook' => [
        'secret' => env('RESEND_WEBHOOK_SECRET') ?: env('RESEND_WEBHOOK_SIGNING_SECRET'),
        'tolerance' => is_numeric($webhookTolerance) ? (int) $webhookTolerance : 300,
    ],
];
