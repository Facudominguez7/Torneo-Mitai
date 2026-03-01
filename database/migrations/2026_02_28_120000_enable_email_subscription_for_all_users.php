<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activa la suscripción por email para todos los usuarios existentes.
     *
     * Se aplica para maximizar el alcance de notificaciones y permitir que
     * cada usuario decida su baja mediante el enlace de desuscripción.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_subscribed')) {
            return;
        }

        DB::table('users')->update([
            'email_subscribed' => true,
        ]);
    }

    /**
     * No revierte cambios de datos por tratarse de migración operacional.
     */
    public function down(): void
    {
        // Sin reversión intencional.
    }
};
