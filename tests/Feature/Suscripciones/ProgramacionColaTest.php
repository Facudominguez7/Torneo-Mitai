<?php

// Sin worker permanente en el hosting: el scheduler (cron cada minuto) procesa la cola.

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function tareaProgramada(string $comando): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $evento): bool => str_contains((string) $evento->command, $comando));
}

it('procesa la cola cada minuto hasta vaciarla, sin superponerse', function (): void {
    $worker = tareaProgramada('queue:work');

    expect($worker)->not->toBeNull()
        ->and($worker->command)->toContain('--stop-when-empty')->toContain('--max-time=50')
        ->and($worker->expression)->toBe('* * * * *')
        ->and($worker->withoutOverlapping)->toBeTrue();
});

it('retoma cada hora los suscriptores sin sincronizar', function (): void {
    $comando = tareaProgramada('suscripciones:sincronizar');

    expect($comando)->not->toBeNull()
        ->and($comando->expression)->toBe('0 * * * *')
        ->and($comando->withoutOverlapping)->toBeTrue();
});
