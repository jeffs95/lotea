<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CajaFactory extends Factory
{
    protected $model = \App\Models\Caja::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Caja chica',
            'tipo' => 'efectivo',
            'moneda' => 'GTQ',
            'saldo_inicial' => 0,
            'activa' => true,
        ];
    }

    public function enDolares(): static
    {
        return $this->state(['nombre' => 'Cuenta dólares', 'tipo' => 'banco', 'moneda' => 'USD']);
    }
}
