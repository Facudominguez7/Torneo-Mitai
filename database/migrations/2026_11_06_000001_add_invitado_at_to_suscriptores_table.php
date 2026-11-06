<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Desde esta fecha las altas con cuenta son invitaciones del comando, no pedidos desde el sitio. */
    private const CORTE_INVITACIONES = '2026-11-06 00:00:00';

    /**
     * Momento en que se invitó a esa persona a suscribirse. Permite distinguir a los invitados del comando
     * de quienes se suscribieron desde el sitio, y contar en el panel cómo vienen esas invitaciones.
     */
    public function up(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->timestamp('invitado_at')->nullable()->after('confirmado_at');
        });

        $this->marcarInvitacionesPrevias();
    }

    /**
     * Marca las invitaciones que ya salieron: las del primer lote, reenviado a mano el 2026-11-06,
     * y las de las corridas siguientes. Un alta con cuenta que sigue pendiente vino del comando;
     * si ya confirmó, se suscribió por su cuenta y no es una invitación.
     */
    public function marcarInvitacionesPrevias(): void
    {
        DB::table('suscriptores')
            ->whereNotNull('usuario_id')
            ->whereNull('confirmado_at')
            ->where('created_at', '>=', self::CORTE_INVITACIONES)
            ->update(['invitado_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('suscriptores', function (Blueprint $table): void {
            $table->dropColumn('invitado_at');
        });
    }
};
