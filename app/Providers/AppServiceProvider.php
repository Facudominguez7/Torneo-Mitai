<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Event as FacadesEvent;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
