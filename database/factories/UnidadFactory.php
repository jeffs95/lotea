<?php

namespace Database\Factories;

use App\Enums\EstadoUnidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadFactory extends Factory
{
    protected $model = \App\Models\Unidad::class;

    public function definition(): array
    {
        return [
            'vin' => strtoupper(fake()->unique()->bothify('?#?#?#?#?#?#?#?#?')),
            'stock_no' => (string) fake()->unique()->numberBetween(1000, 9999),
            'anio' => fake()->numberBetween(2010, 2024),
            'color' => fake()->safeColorName(),
            'odometro' => fake()->numberBetween(10000, 180000),
            'tipo_titulo' => fake()->randomElement(['clean', 'salvage', 'rebuilt']),
            'estado' => EstadoUnidad::Comprada,
            'estado_desde' => now(),
            'fecha_compra' => now()->subDays(fake()->numberBetween(1, 120)),
        ];
    }
}
