<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = \App\Models\Cliente::class;

    public function definition(): array
    {
        return [
            'tipo' => 'persona',
            'nombre' => fake()->name(),
            'nit' => fake()->numerify('#######-#'),
            'telefono' => fake()->numerify('####-####'),
            'email' => fake()->safeEmail(),
        ];
    }
}
