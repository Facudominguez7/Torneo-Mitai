<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('goles_confirmados_at');
        });

        Schema::table('instancias_finales', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('goles_confirmados_at');
        });

        $this->generarTokens('partidos');
        $this->generarTokens('instancias_finales');

        DB::statement('ALTER TABLE partidos MODIFY public_token CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE instancias_finales MODIFY public_token CHAR(36) NOT NULL');

        Schema::table('partidos', function (Blueprint $table) {
            $table->unique('public_token');
        });

        Schema::table('instancias_finales', function (Blueprint $table) {
            $table->unique('public_token');
        });
    }

    private function generarTokens(string $tabla): void
    {
        $tokens = [];
        $registros = DB::table($tabla)->whereNull('public_token')->get();

        foreach ($registros as $registro) {
            $tokens[$registro->id] = (string) Str::uuid();
        }

        foreach ($tokens as $id => $token) {
            DB::table($tabla)->where('id', $id)->update(['public_token' => $token]);
        }
    }

    public function down(): void
    {
        Schema::table('partidos', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });

        Schema::table('instancias_finales', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
