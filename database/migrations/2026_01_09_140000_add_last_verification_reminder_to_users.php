<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo de seguimiento de recordatorios de verificación.
 *
 * Este campo permite controlar cuándo se envió el último recordatorio
 * de verificación de email a cada usuario, evitando enviar spam.
 */
return new class extends Migration
{
    /**
     * Ejecutar la migración.
     *
     * Agrega la columna 'last_verification_reminder_at' para trackear
     * cuándo se envió el último recordatorio de verificación de email.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_verification_reminder_at')
                ->nullable()
                ->after('email_subscribed')
                ->comment('Fecha del último recordatorio de verificación enviado');
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_verification_reminder_at');
        });
    }
};
