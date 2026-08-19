<?php

namespace Database\Seeders;

use App\Enums\EstadoUnidad;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\UnidadTransicion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Unidades de demostración con datos plausibles de subasta.
 *
 * No son datos de relleno: los precios, daños y modelos son los que de verdad
 * se traen de Copart a Guatemala, porque un demo con "Marca 1 / Modelo A" no
 * le dice nada a un dueño de concesionario.
 */
class UnidadesDemoSeeder extends Seeder
{
    public const FLOTA = [
        // marca, línea, versión, año, color, odómetro, título, daño, estado, precio de lista
        ['Toyota', 'RAV4', 'XLE', 2019, 'Blanco', 62400, 'salvage', 'Front end', EstadoUnidad::Publicada, 148000],
        ['Toyota', 'Corolla', 'LE', 2020, 'Gris', 48200, 'clean', null, EstadoUnidad::Publicada, 112000],
        ['Honda', 'CR-V', 'EX', 2018, 'Negro', 78900, 'salvage', 'Rear end', EstadoUnidad::Lista, 135000],
        ['Honda', 'Civic', 'Sport', 2021, 'Rojo', 31500, 'clean', null, EstadoUnidad::Reservada, 128000],
        ['Nissan', 'Sentra', 'SV', 2019, 'Plata', 71200, 'salvage', 'Side', EstadoUnidad::EnTaller, 89000],
        ['Nissan', 'Frontier', 'SV', 2017, 'Azul', 94000, 'clean', null, EstadoUnidad::Publicada, 155000],
        ['Hyundai', 'Tucson', 'SEL', 2020, 'Blanco', 45800, 'salvage', 'Hail', EstadoUnidad::Recibida, 138000],
        ['Kia', 'Sportage', 'LX', 2019, 'Gris', 66300, 'salvage', 'Front end', EstadoUnidad::EnAduana, 125000],
        ['Mazda', 'CX-5', 'Touring', 2018, 'Rojo', 82100, 'clean', null, EstadoUnidad::EnTaller, 132000],
        ['Toyota', 'Tacoma', 'SR5', 2016, 'Negro', 118000, 'salvage', 'Rollover', EstadoUnidad::Embarcada, 168000],
        ['Chevrolet', 'Silverado', 'LT', 2018, 'Blanco', 99500, 'salvage', 'Front end', EstadoUnidad::TransitoUsa, 175000],
        ['Ford', 'Escape', 'SE', 2020, 'Azul', 52700, 'clean', null, EstadoUnidad::BodegaUsa, 118000],
        ['Jeep', 'Grand Cherokee', 'Laredo', 2017, 'Gris', 87400, 'salvage', 'Water/flood', EstadoUnidad::Comprada, 145000],
        ['Toyota', 'Prius', 'Two', 2019, 'Blanco', 58300, 'clean', null, EstadoUnidad::Entregada, 98000],
        ['Honda', 'Fit', 'LX', 2018, 'Plata', 69800, 'salvage', 'Minor dent', EstadoUnidad::Entregada, 82000],
    ];

    public function run(): void
    {
        $sucursal = Sucursal::first();

        foreach (self::FLOTA as $i => [$marca, $linea, $version, $anio, $color, $odometro, $titulo, $dano, $estado, $precio]) {
            $modeloMarca = Marca::where('nombre', $marca)->first();
            $modeloLinea = Linea::where('marca_id', $modeloMarca?->id)->where('nombre', $linea)->first();

            $diasDesdeCompra = 15 + ($i * 9);
            $fechaCompra = now()->subDays($diasDesdeCompra);

            $unidad = Unidad::create([
                'sucursal_id' => $sucursal?->id,
                'vin' => Str::upper(Str::random(11)).str_pad((string) (100000 + $i), 6, '0'),
                'stock_no' => str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'marca_id' => $modeloMarca?->id,
                'linea_id' => $modeloLinea?->id,
                'version' => $version,
                'anio' => $anio,
                'color' => $color,
                'odometro' => $odometro,
                'odometro_unidad' => 'mi',
                'tipo_titulo' => $titulo,
                'tipo_dano' => $dano,
                'estado' => $estado,
                'estado_desde' => now()->subDays(max(1, (int) ($diasDesdeCompra / 4))),
                'fecha_compra' => $fechaCompra,
                'precio_lista' => $precio,
                'precio_minimo' => round($precio * 0.92, 2),
                'publicado' => in_array($estado, [EstadoUnidad::Publicada, EstadoUnidad::Reservada], true),
                'slug' => Str::slug("{$marca} {$linea} {$anio} stock ".($i + 1)),
            ]);

            UnidadTransicion::create([
                'unidad_id' => $unidad->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoUnidad::Comprada,
                'ocurrio_en' => $fechaCompra,
                'nota' => 'Unidad registrada',
            ]);

            if ($estado !== EstadoUnidad::Comprada) {
                UnidadTransicion::create([
                    'unidad_id' => $unidad->id,
                    'estado_anterior' => EstadoUnidad::Comprada,
                    'estado_nuevo' => $estado,
                    'ocurrio_en' => $unidad->estado_desde,
                    'dias_en_estado_anterior' => (int) $fechaCompra->diffInDays($unidad->estado_desde),
                    'nota' => 'Carga inicial de demostración',
                ]);
            }
        }
    }
}
