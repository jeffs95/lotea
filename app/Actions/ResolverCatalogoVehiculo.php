<?php

namespace App\Actions;

use App\Models\Linea;
use App\Models\Marca;
use Illuminate\Support\Str;

/**
 * Convierte "Toyota" y "RAV4" en los ids del catálogo.
 *
 * Si la marca o la línea no existen, se crean para ese concesionario en lugar
 * de descartar el dato: es preferible un catálogo que crece a una ficha
 * incompleta. Las del sistema se reutilizan tal cual.
 */
class ResolverCatalogoVehiculo
{
    /** @return array{marca_id: ?int, linea_id: ?int} */
    public function ejecutar(?string $marca, ?string $linea): array
    {
        $modeloMarca = filled($marca) ? $this->marca($marca) : null;

        return [
            'marca_id' => $modeloMarca?->id,
            'linea_id' => ($modeloMarca && filled($linea)) ? $this->linea($modeloMarca, $linea)->id : null,
        ];
    }

    protected function marca(string $nombre): Marca
    {
        $slug = Str::slug($nombre);

        return Marca::where('slug', $slug)->first()
            ?? Marca::create(['nombre' => Str::title($nombre), 'slug' => $slug, 'activo' => true]);
    }

    protected function linea(Marca $marca, string $nombre): Linea
    {
        $slug = Str::slug($nombre);

        return Linea::where('marca_id', $marca->id)->where('slug', $slug)->first()
            ?? Linea::create([
                'marca_id' => $marca->id,
                'nombre' => Str::title($nombre),
                'slug' => $slug,
                'activo' => true,
            ]);
    }
}
