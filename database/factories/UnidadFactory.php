<?php

namespace Database\Factories;

use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadFactory extends Factory
{
    protected $model = Unidad::class;

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

    /**
     * Unidad lista para el portal: con precio y con una foto de verdad.
     *
     * El modelo despublica sola a la que no cumpla, así que pedirle a la
     * factory `'publicado' => true` a secas devolvería una unidad no publicada
     * y el test mentiría. Este state hace el trabajo completo.
     */
    public function publicada(): static
    {
        return $this
            ->state(fn () => [
                'estado' => EstadoUnidad::Publicada,
                'precio_lista' => fake()->numberBetween(80, 200) * 1000,
            ])
            ->afterCreating(function (Unidad $unidad) {
                $unidad->addMediaFromString($this->imagenMinima())
                    ->usingFileName('foto-'.$unidad->id.'.png')
                    ->toMediaCollection('fotos');

                $unidad->refresh()->update(['publicado' => true]);
            });
    }

    /**
     * Una imagen chica pero real.
     *
     * Un PNG de 1x1 no sirve: medialibrary genera sus conversiones y no puede
     * redimensionar algo de ese tamaño.
     */
    protected function imagenMinima(): string
    {
        $imagen = imagecreatetruecolor(160, 120);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 210, 210, 215));

        ob_start();
        imagepng($imagen);
        $binario = ob_get_clean();
        imagedestroy($imagen);

        return $binario;
    }
}
