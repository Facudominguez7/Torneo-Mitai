<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

class EmailDomainGuard
{
    private const DEFAULT_BLOCKED_DOMAINS = [
        'bonsoirmail.com',
        'bubuk.site',
        'eblanomail.com',
        'example.com',
        'gamail.com',
        'mail.ru',
    ];

    /**
     * Obtiene la lista de dominios bloqueados normalizada.
     *
     * @return array<int, string>
     */
    public static function blockedDomains(): array
    {
        $domains = Config::get('email_hygiene.blocked_domains', self::DEFAULT_BLOCKED_DOMAINS);

        $normalizados = [];
        foreach ($domains as $domain) {
            if (! is_string($domain) || trim($domain) === '') {
                continue;
            }

            $normalizados[] = mb_strtolower(trim($domain), 'UTF-8');
        }

        return array_values(array_unique(array_merge(self::DEFAULT_BLOCKED_DOMAINS, $normalizados)));
    }

    public static function isBlocked(?string $email): bool
    {
        $domain = self::extractDomain($email);

        return $domain !== null && in_array($domain, self::blockedDomains(), true);
    }

    public static function extractDomain(?string $email): ?string
    {
        if (! is_string($email) || trim($email) === '' || ! str_contains($email, '@')) {
            return null;
        }

        return mb_strtolower(trim((string) substr(strrchr($email, '@'), 1)), 'UTF-8');
    }

    public static function applyBlockedDomainsFilter(Builder $query, string $column = 'email'): Builder
    {
        foreach (self::blockedDomains() as $domain) {
            $query->whereRaw('LOWER('.$column.') NOT LIKE ?', ['%@'.$domain]);
        }

        return $query;
    }

    public static function validationMessage(): string
    {
        return 'No se aceptan registros con ese dominio de correo.';
    }
}
