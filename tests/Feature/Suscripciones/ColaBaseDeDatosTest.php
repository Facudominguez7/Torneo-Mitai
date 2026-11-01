<?php

// La cola de base de datos necesita la columna available_at (la migración original la escribió mal).

use App\Jobs\SincronizarSuscriptorResend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('la tabla jobs tiene la columna que usa la cola de base de datos', function (): void {
    expect(Schema::hasColumn('jobs', 'available_at'))->toBeTrue()
        ->and(Schema::hasColumn('jobs', 'availabe_at'))->toBeFalse();
});

it('la cola de base de datos puede guardar un job', function (): void {
    config(['queue.default' => 'database']);

    SincronizarSuscriptorResend::dispatch(123);

    expect(DB::table('jobs')->count())->toBe(1);
});
