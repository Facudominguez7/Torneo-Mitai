<?php

namespace App\Services;

use App\Models\Suscriptor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Resend\Client;
use Resend\Exceptions\ErrorException;
use Throwable;

/**
 * Lleva el estado de cada suscriptor a Resend: contacto, segmento de los masivos y tópicos de cada aviso.
 * Corre en la misma petición (producción no tiene worker); si falla, el suscriptor queda pendiente
 * y el comando suscripciones:sincronizar lo reintenta.
 */
class SincronizacionResendService
{
    public function __construct(private readonly Client $resend) {}

    /** @return bool true si Resend quedó con el estado actual del suscriptor */
    public function sincronizar(Suscriptor $suscriptor): bool
    {
        if (! config('suscripciones.resend.sincronizar') || $suscriptor->confirmado_at === null) {
            return false;
        }

        try {
            $suscriptor->estaActivo() ? $this->publicar($suscriptor) : $this->marcarBaja($suscriptor);
        } catch (Throwable $error) {
            // Sin el mensaje de Resend: puede incluir el correo del suscriptor.
            Log::warning('No se pudo sincronizar el suscriptor con Resend.', [
                'suscriptor_id' => $suscriptor->id,
                'error' => $error::class,
                'codigo' => $error->getCode(),
            ]);

            return false;
        }

        $suscriptor->forceFill(['resend_sincronizado_at' => now()])->save();

        return true;
    }

    private function publicar(Suscriptor $suscriptor): void
    {
        $segmento = (string) config('suscripciones.resend.segmento');
        $idContacto = $suscriptor->resend_contacto_id;

        if ($idContacto === null) {
            $idContacto = $this->contactoExistente($suscriptor->email);

            if ($idContacto === null) {
                $contacto = $this->resend->contacts->create([
                    'email' => $suscriptor->email,
                    'unsubscribed' => false,
                    'segments' => [['id' => $segmento]],
                    'topics' => $this->topicos($suscriptor),
                    'properties' => $this->propiedades($suscriptor),
                ]);
                $suscriptor->forceFill(['resend_contacto_id' => $contacto->id])->save();

                return;
            }

            // Se guarda ya: si falla un paso siguiente, una baja posterior tiene que encontrar este contacto.
            $suscriptor->forceFill(['resend_contacto_id' => $idContacto])->save();
            $this->resend->contacts->update($idContacto, ['unsubscribed' => false, 'properties' => $this->propiedades($suscriptor)]);
            $this->resend->contacts->segments->add($idContacto, $segmento);
        } else {
            $this->resend->contacts->update($idContacto, ['unsubscribed' => false, 'properties' => $this->propiedades($suscriptor)]);
        }

        $this->resend->contacts->topics->update($idContacto, $this->topicos($suscriptor));
    }

    /**
     * Sin id guardado igual se busca por correo: el contacto puede existir allá aunque la respuesta
     * de un alta anterior se haya perdido (timeout) o venir de otra fuente.
     */
    private function marcarBaja(Suscriptor $suscriptor): void
    {
        $idContacto = $suscriptor->resend_contacto_id ?? $this->contactoExistente($suscriptor->email);

        if ($idContacto === null) {
            return;
        }

        $suscriptor->forceFill(['resend_contacto_id' => $idContacto])->save();
        $this->resend->contacts->update($idContacto, ['unsubscribed' => true]);
    }

    private function contactoExistente(string $email): ?string
    {
        try {
            return $this->resend->contacts->get($email)->id;
        } catch (ErrorException $error) {
            if ($error->getErrorCode() === 404) {
                return null;
            }

            throw $error;
        }
    }

    /**
     * Propiedades del contacto que usan los broadcasts: el HTML es el mismo para todos, así que cada
     * contacto lleva su enlace firmado a preferencias (el mismo de SuscripcionService::urlPreferencias).
     *
     * @return array{url_preferencias: string}
     */
    private function propiedades(Suscriptor $suscriptor): array
    {
        return ['url_preferencias' => URL::signedRoute('suscripcion.preferencias', $suscriptor)];
    }

    /** @return list<array{id: string, subscription: string}> */
    private function topicos(Suscriptor $suscriptor): array
    {
        $topicos = [];
        foreach (config('suscripciones.avisos') as $aviso => $columna) {
            $topicos[] = [
                'id' => (string) config("suscripciones.resend.topicos.{$aviso}"),
                'subscription' => $suscriptor->{$columna} ? 'opt_in' : 'opt_out',
            ];
        }

        return $topicos;
    }
}
