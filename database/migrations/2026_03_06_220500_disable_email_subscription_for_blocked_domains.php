<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Snapshot de dominios bloqueados al momento de crear la migración.
     *
     * Se desuscriben de emails automáticos, pero no se elimina ninguna cuenta.
     * Esto permite una limpieza controlada y reversible a nivel operativo.
     *
     * @var array<int, string>
     */
    private array $blockedDomains = [
        'bonsoirmail.com',
        'bubuk.site',
        'eblanomail.com',
        'example.com',
        'gamail.com',
        'mail.ru',
    ];

    public function up(): void
    {
        $query = DB::table('users')
            ->where('email_subscribed', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                foreach ($this->blockedDomains as $index => $domain) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';

                    $query->{$method}('LOWER(email) LIKE ?', ['%@'.$domain]);
                }
            });

        $query->update([
            'email_subscribed' => false,
        ]);
    }

    public function down(): void
    {
    }
};
