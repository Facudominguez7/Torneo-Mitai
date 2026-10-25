<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento en que Resend recibió el último estado del suscriptor.
     * En null hay un cambio sin llevar (por ejemplo, una baja) y el comando suscripciones:sincronizar lo reintenta.
     */
    public function up(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->timestamp('resend_sincronizado_at')->nullable()->after('resend_contacto_id');
        });
    }

    public function down(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->dropColumn('resend_sincronizado_at');
        });
    }
};
