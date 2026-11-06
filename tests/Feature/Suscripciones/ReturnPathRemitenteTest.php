<?php

use App\Mail\InvitacionSuscripcionMail;
use App\Services\EmailDeliverabilityHeaders;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config([
        'mail.from.address' => 'info@torneosmitai.com',
        'suscripciones.remitente' => 'avisos@notificaciones.torneosmitai.com',
    ]);
});

it('uses the subscriber sender as Return-Path so Resend only sees the verified domain', function () {
    (new InvitacionSuscripcionMail('https://torneosmitai.com/confirmar'))->to('persona@correo.test')->send(Mail::mailer('array'));

    /** @var Email $mensaje */
    $mensaje = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect($mensaje->getFrom()[0]->getAddress())->toBe('avisos@notificaciones.torneosmitai.com')
        ->and($mensaje->getReturnPath()?->getAddress())->toBe('avisos@notificaciones.torneosmitai.com');
});

it('keeps the account sender as Return-Path for account emails', function () {
    $mensaje = (new Email)->from('info@torneosmitai.com')->to('persona@correo.test')->text('Hola');

    EmailDeliverabilityHeaders::applyTransactional($mensaje, 'verificacion_email');

    expect($mensaje->getReturnPath()?->getAddress())->toBe('info@torneosmitai.com');
});

it('falls back to the configured sender when the message has no From yet', function () {
    $mensaje = (new Email)->to('persona@correo.test')->text('Hola');

    EmailDeliverabilityHeaders::applyBulk($mensaje, 'avisos_torneo');

    expect($mensaje->getReturnPath()?->getAddress())->toBe('info@torneosmitai.com');
});
