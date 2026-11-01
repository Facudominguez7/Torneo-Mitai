<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento (epoch en milisegundos) del último cambio que la persona hizo en el sitio. Decide qué
     * evento del webhook de Resend es más viejo que ese cambio, y si una sincronización en curso
     * todavía corresponde al estado guardado. En milisegundos porque Resend fecha sus eventos así.
     */
    public function up(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->unsignedBigInteger('cambio_local_ms')->nullable()->after('resend_sincronizado_at');
        });
    }

    public function down(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->dropColumn('cambio_local_ms');
        });
    }
};
