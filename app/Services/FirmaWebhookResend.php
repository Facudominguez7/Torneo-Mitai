<?php

namespace App\Services;

/**
 * Verifica la firma de los webhooks de Resend, que firma con Svix: HMAC-SHA256 en base64 de
 * "{svix-id}.{svix-timestamp}.{cuerpo crudo}", con el secreto (whsec_...) decodificado de base64.
 */
class FirmaWebhookResend
{
    public function esValida(string $cuerpo, ?string $id, ?string $timestamp, ?string $firmas): bool
    {
        $secreto = (string) config('suscripciones.resend.webhook_secreto');

        if ($secreto === '' || $id === null || $timestamp === null || $firmas === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > (int) config('suscripciones.resend.webhook_tolerancia_segundos')) {
            return false;
        }

        $clave = base64_decode(str_starts_with($secreto, 'whsec_') ? substr($secreto, 6) : $secreto, true);
        if ($clave === false) {
            return false;
        }

        $esperada = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$cuerpo}", $clave, true));

        // El encabezado puede traer varias firmas ("v1,xxx v1,yyy") mientras se rota el secreto.
        foreach (explode(' ', $firmas) as $firma) {
            [$version, $valor] = array_pad(explode(',', $firma, 2), 2, '');

            if ($version === 'v1' && hash_equals($esperada, $valor)) {
                return true;
            }
        }

        return false;
    }
}
