<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de los avisos masivos a suscriptores. La clave única (tipo + fecha + categoría) es
     * también el candado: un resumen ya enviado o en curso no se puede volver a enviar.
     */
    public function up(): void
    {
        Schema::create('envios_suscriptores', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo', 40);
            $table->string('clave', 120)->unique();
            $table->unsignedBigInteger('idEdicion');
            $table->unsignedBigInteger('idFecha')->nullable();
            $table->unsignedBigInteger('idCategoria')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20);
            $table->unsignedInteger('destinatarios')->default(0);
            $table->string('broadcast_id')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_suscriptores');
    }
};
