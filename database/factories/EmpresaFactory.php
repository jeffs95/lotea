<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        $nombre = fake()->company();

        return [
            'nombre' => $nombre,
            'nombre_comercial' => $nombre,
            'nit' => fake()->numerify('########-#'),
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numberBetween(1, 99999),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'moneda_base' => 'GTQ',
            'plan' => 'basico',
            'activa' => true,
        ];
    }
}
