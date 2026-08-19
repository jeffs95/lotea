<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Marcas y líneas que Lotea mantiene para todos los clientes (empresa_id null).
 *
 * La lista está sesgada a propósito hacia lo que de verdad se importa de
 * subasta a Guatemala; no es un catálogo mundial de automóviles.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $catalogo = [
            'Toyota' => ['Corolla', 'RAV4', 'Hilux', 'Tacoma', 'Camry', 'Prius', 'Yaris', 'Highlander', '4Runner', 'Tundra', 'Sienna', 'C-HR'],
            'Honda' => ['Civic', 'CR-V', 'Accord', 'Fit', 'HR-V', 'Pilot', 'Odyssey', 'Ridgeline'],
            'Nissan' => ['Sentra', 'Versa', 'Altima', 'Rogue', 'Kicks', 'Frontier', 'X-Trail', 'Murano', 'Pathfinder'],
            'Hyundai' => ['Elantra', 'Tucson', 'Santa Fe', 'Accent', 'Kona', 'Sonata', 'Creta'],
            'Kia' => ['Rio', 'Sportage', 'Sorento', 'Forte', 'Seltos', 'Soul', 'Picanto'],
            'Mazda' => ['Mazda3', 'CX-5', 'Mazda6', 'CX-3', 'CX-30', 'BT-50'],
            'Chevrolet' => ['Spark', 'Cruze', 'Equinox', 'Silverado', 'Tahoe', 'Traverse', 'Colorado'],
            'Ford' => ['F-150', 'Escape', 'Explorer', 'Focus', 'Ranger', 'Edge', 'Fusion'],
            'Volkswagen' => ['Jetta', 'Tiguan', 'Golf', 'Passat', 'Amarok'],
            'Mitsubishi' => ['Montero', 'L200', 'Outlander', 'Lancer', 'ASX'],
            'Suzuki' => ['Swift', 'Vitara', 'Jimny', 'Baleno'],
            'Jeep' => ['Grand Cherokee', 'Wrangler', 'Compass', 'Cherokee', 'Renegade'],
            'BMW' => ['Serie 3', 'Serie 5', 'X3', 'X5'],
            'Mercedes-Benz' => ['Clase C', 'Clase E', 'GLC', 'GLE'],
            'Isuzu' => ['D-Max', 'MU-X'],
            'Subaru' => ['Forester', 'Outback', 'Impreza', 'XV'],
            'RAM' => ['1500', '2500', 'ProMaster'],
            'GMC' => ['Sierra', 'Terrain', 'Yukon'],
            'Audi' => ['A3', 'A4', 'Q5'],
            'Lexus' => ['RX', 'NX', 'ES'],
        ];

        foreach ($catalogo as $marca => $lineas) {
            $modeloMarca = Marca::withoutGlobalScopes()->updateOrCreate(
                ['empresa_id' => null, 'slug' => Str::slug($marca)],
                ['nombre' => $marca, 'activo' => true],
            );

            foreach ($lineas as $linea) {
                $modeloMarca->lineas()->withoutGlobalScopes()->updateOrCreate(
                    ['empresa_id' => null, 'slug' => Str::slug($linea)],
                    ['nombre' => $linea, 'activo' => true],
                );
            }
        }
    }
}
