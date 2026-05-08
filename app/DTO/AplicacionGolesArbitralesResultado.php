<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class AplicacionGolesArbitralesResultado
{
    /**
     * @param  array<int, array<string, mixed>>  $resultados
     */
    public function __construct(
        public string $status,
        public string $message,
        public bool $huboCambiosReales,
        public bool $confirmacionInvalidada,
        public array $resultados,
        public ?string $advertencia,
    ) {}
}
