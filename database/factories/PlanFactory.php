<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $nombre = 'Plan '.fake()->unique()->word();

        return [
            'nombre' => $nombre,
            'slug' => str($nombre)->slug()->value(),
            'precio_mensual' => fake()->randomElement([595, 895, 1295]),
            'modulos' => ['unidades', 'ventas', 'caja'],
            'activo' => true,
        ];
    }

    /** Plan que incluye el add-on de lectura de documentos, con su tope. */
    public function conIa(?int $tope = null): static
    {
        return $this->state(fn (array $atributos) => [
            'modulos' => [...$atributos['modulos'], 'ia'],
            'max_lecturas_ia' => $tope,
        ]);
    }

    /** Plan sin el add-on: el botón de leer documentos tiene que estar cerrado. */
    public function sinIa(): static
    {
        return $this->state(fn (array $atributos) => [
            'modulos' => array_values(array_diff($atributos['modulos'], ['ia'])),
        ]);
    }
}
