<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

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
