<?php

namespace Database\Factories;

use App\Models\Caja;
use Illuminate\Database\Eloquent\Factories\Factory;

class CajaFactory extends Factory
{
    protected $model = Caja::class;

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
