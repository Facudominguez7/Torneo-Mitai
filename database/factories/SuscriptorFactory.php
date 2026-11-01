<?php

namespace Database\Factories;

use App\Models\Suscriptor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suscriptor>
 */
class SuscriptorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->userName().'@correo.com',
        ];
    }

    public function pendiente(): static
    {
        return $this->state([]);
    }

    public function activo(): static
    {
        return $this->afterMaking(fn (Suscriptor $suscriptor) => $suscriptor->forceFill(['confirmado_at' => now()->subWeek()]));
    }

    public function deBaja(): static
    {
        return $this->afterMaking(fn (Suscriptor $suscriptor) => $suscriptor->forceFill([
            'confirmado_at' => now()->subMonth(),
            'baja_at' => now()->subWeek(),
        ]));
    }
}
