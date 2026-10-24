<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar campo de suscripción a notificaciones por email.
 *
 * Este campo permite a los usuarios optar por no recibir notificaciones
 * por correo electrónico mientras mantienen activas las notificaciones push.
 * Por defecto, todos los usuarios están suscritos a emails.
 */
return new class extends Migration
{
    /**
     * Ejecutar la migración.
     *
     * Agrega la columna 'email_subscribed' a la tabla users.
     * - true (default): El usuario recibe notificaciones por email
     * - false: El usuario no recibe notificaciones por email
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Columna booleana para controlar suscripción a emails
            // Por defecto es true (todos reciben emails al registrarse)
            $table->boolean('email_subscribed')
                ->default(true)
                ->after('email_verified_at')
                ->comment('Indica si el usuario desea recibir notificaciones por email');
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_subscribed');
        });
    }
};
