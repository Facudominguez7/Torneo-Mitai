<?php

use Illuminate\Support\Facades\DB;

it('usa la base de datos de testing configurada', function () {
    $conexionPorDefecto = config('database.default');
    $baseDeDatosEsperada = env('DB_TEST_DATABASE', 'mitai_testing');
    $conexion = DB::connection($conexionPorDefecto);

    expect(config("database.connections.{$conexionPorDefecto}.database"))->toBe($baseDeDatosEsperada);
    expect($conexion->getDatabaseName())->toBe($baseDeDatosEsperada);
    expect($conexion->selectOne('select database() as db')->db)->toBe($baseDeDatosEsperada);
});
