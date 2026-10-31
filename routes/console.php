<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// El hosting no admite un worker permanente: un cron corre schedule:run cada minuto y este worker
// procesa la cola hasta vaciarla, sin pasar el minuto.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Red de seguridad: retoma los suscriptores que quedaron sin sincronizar con Resend.
Schedule::command('suscripciones:sincronizar')
    ->hourly()
    ->withoutOverlapping();
