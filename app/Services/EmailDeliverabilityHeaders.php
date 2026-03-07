<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;

class EmailDeliverabilityHeaders
{
    public static function fromAddress(): string
    {
        return (string) Config::get('mail.from.address', 'info@torneosmitai.com');
    }

    public static function fromName(): string
    {
        return (string) Config::get('mail.from.name', 'Torneos Mitai');
    }

    public static function replyToAddress(): string
    {
        $replyTo = (string) Config::get('mail.reply_to.address', '');

        return $replyTo !== '' ? $replyTo : self::fromAddress();
    }

    public static function replyToName(): string
    {
        $replyToName = (string) Config::get('mail.reply_to.name', '');

        return $replyToName !== '' ? $replyToName : self::fromName();
    }

    public static function applyBulk(Email $message, string $categoria, ?string $unsubscribeUrl = null): void
    {
        self::applyBaseHeaders($message, $categoria, true);

        $headers = $message->getHeaders();
        self::replaceHeader($headers, 'Precedence', 'bulk');
        self::replaceHeader($headers, 'List-ID', self::listId());

        if ($unsubscribeUrl) {
            self::replaceHeader($headers, 'List-Unsubscribe', "<{$unsubscribeUrl}>");
            self::replaceHeader($headers, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
    }

    public static function applyTransactional(Email $message, string $categoria, ?string $unsubscribeUrl = null): void
    {
        self::applyBaseHeaders($message, $categoria, false);

        if ($unsubscribeUrl) {
            $headers = $message->getHeaders();
            self::replaceHeader($headers, 'List-Unsubscribe', "<{$unsubscribeUrl}>");
            self::replaceHeader($headers, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
    }

    private static function applyBaseHeaders(Email $message, string $categoria, bool $bulk): void
    {
        $headers = $message->getHeaders();
        $feedbackCategory = Str::slug($categoria, '_');

        if ($replyTo = self::replyToAddress()) {
            $message->replyTo($replyTo);
        }

        if ($returnPath = self::fromAddress()) {
            $message->returnPath($returnPath);
        }

        self::replaceHeader($headers, 'X-Auto-Response-Suppress', 'All');
        self::replaceHeader($headers, 'Auto-Submitted', 'auto-generated');
        self::replaceHeader($headers, 'X-Entity-Ref-ID', self::entityReference($feedbackCategory));
        self::replaceHeader(
            $headers,
            'Feedback-ID',
            "{$feedbackCategory}:".self::feedbackIdentifier().':'.($bulk ? 'bulk' : 'transactional')
        );
    }

    private static function replaceHeader(Headers $headers, string $name, string $value): void
    {
        if ($headers->has($name)) {
            $headers->remove($name);
        }

        $headers->addTextHeader($name, $value);
    }

    private static function entityReference(string $categoria): string
    {
        return $categoria.'-'.Str::uuid().'@'.self::mailDomain();
    }

    private static function feedbackIdentifier(): string
    {
        return (string) Config::get('mail.bulk.feedback_identifier', 'torneosmitai');
    }

    private static function listId(): string
    {
        $listId = (string) Config::get('mail.bulk.list_id', 'notificaciones.torneosmitai.com');

        return 'Torneos Mitai <'.$listId.'>';
    }

    private static function mailDomain(): string
    {
        $appHost = parse_url((string) Config::get('app.url', 'https://torneosmitai.com'), PHP_URL_HOST);

        return $appHost ?: 'torneosmitai.com';
    }
}
