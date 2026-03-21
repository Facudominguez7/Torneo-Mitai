<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->configurarBaseDeDatosDeTesting($app);

        return $app;
    }

    private function configurarBaseDeDatosDeTesting($app): void
    {
        $conexion = $app['config']->get('database.default');
        $configuracion = $app['config']->get("database.connections.{$conexion}");

        if (($configuracion['driver'] ?? null) !== 'mysql') {
            return;
        }

        $baseDeDatosTesting = (string) env('DB_TEST_DATABASE', 'mitai_testing');

        $app['config']->set("database.connections.{$conexion}.database", $baseDeDatosTesting);
        $app['db']->purge($conexion);
    }
}
