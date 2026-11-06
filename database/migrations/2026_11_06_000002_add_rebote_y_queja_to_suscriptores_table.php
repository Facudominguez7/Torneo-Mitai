<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Momento en que Resend informó un rebote de ese correo y momento en que la persona lo marcó como spam.
     * Ambos casos dan de baja al suscriptor y quedan a la vista en el panel de suscriptores.
     */
    public function up(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->timestamp('rebote_at')->nullable()->after('baja_at');
            $table->timestamp('queja_at')->nullable()->after('rebote_at');
        });
    }

    public function down(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->dropColumn(['rebote_at', 'queja_at']);
        });
    }
};
