<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instituciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->unsignedBigInteger('idInstitucion')->nullable()->after('id');
            $table->foreign('idInstitucion')->references('id')->on('instituciones')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('equipos', function (Blueprint $table) {
            $table->dropForeign(['idInstitucion']);
            $table->dropColumn('idInstitucion');
        });
        Schema::dropIfExists('instituciones');
    }
};
