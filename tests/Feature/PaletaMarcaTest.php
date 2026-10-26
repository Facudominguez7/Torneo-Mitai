<?php

use Symfony\Component\Finder\Finder;

// AGENTS.md sección 10: el indigo de Breeze no forma parte de la paleta de Mitai.
it('ninguna vista Blade usa el indigo de Breeze en lugar de los tokens de marca', function (): void {
    $vistasConIndigo = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn (SplFileInfo $archivo): bool => str_contains($archivo->getContents(), 'indigo-'))
        ->map(fn (SplFileInfo $archivo): string => $archivo->getRelativePathname())
        ->values()
        ->all();

    expect($vistasConIndigo)->toBe([]);
});
