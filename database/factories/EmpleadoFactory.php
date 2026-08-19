<?php

namespace Database\Factories;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmpleadoFactory extends Factory
{
    protected $model = Empleado::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('EMP-###'),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'puesto' => 'Auxiliar',
            'area' => 'administracion',
            'tipo_contrato' => 'indefinido',
            'fecha_ingreso' => now()->subYears(2),
            'salario_base' => 3500,
            'bonificacion_incentivo' => 250,
            'activo' => true,
        ];
    }

    public function mecanico(): static
    {
        return $this->state([
            'area' => 'taller',
            'puesto' => 'Mecánico',
            'es_mecanico' => true,
            'costo_hora' => 45,
        ]);
    }
}
