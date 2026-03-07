<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'requires_email_verification_for_notifications')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('requires_email_verification_for_notifications')
                    ->default(false)
                    ->comment('Exige verificar el email antes de habilitar notificaciones por correo');
            });
        }

        if (! Schema::hasTable('email_verification_codes')) {
            Schema::create('email_verification_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');

        if (Schema::hasColumn('users', 'requires_email_verification_for_notifications')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('requires_email_verification_for_notifications');
            });
        }
    }
};
