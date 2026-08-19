<?php

namespace Database\Seeders;

use App\Actions\AbrirOrdenTrabajo;
use App\Actions\AgregarLineaOrdenTrabajo;
use App\Enums\EstadoUnidad;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Órdenes abiertas para las unidades que hoy están en el taller.
 *
 * Las que ya salieron traen su costo cargado por CostosDemoSeeder, así que
 * aquí no se les vuelve a abrir orden: se duplicaría el costo.
 */
class TallerDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::firstWhere('slug', 'autos-del-valle'));
        }

        if (OrdenTrabajo::count() > 0) {
            return;
        }

        $mecanicos = Empleado::mecanicos()->get();
        $jefe = $mecanicos->firstWhere('puesto', 'Jefe de taller') ?? $mecanicos->first();

        if ($mecanicos->isEmpty()) {
            return;
        }

        $pintura = Proveedor::firstOrCreate(
            ['tipo' => 'taller', 'nombre' => 'Taller de pintura El Rayo'],
            ['pais' => 'GT', 'moneda_default' => 'GTQ'],
        );

        $repuestera = Proveedor::firstOrCreate(
            ['tipo' => 'repuestos', 'nombre' => 'Repuestos La Reforma'],
            ['pais' => 'GT', 'moneda_default' => 'GTQ'],
        );

        $trabajos = [
            [
                'diagnostico' => 'Golpe frontal de subasta. Cambio de guardafango, faro y trompa. Pintura de dos piezas.',
                'lineas' => [
                    ['mano_obra', 'Desarmado y enderezado de trompa', 8, null],
                    ['mano_obra', 'Armado y ajuste de piezas', 5, null],
                    ['repuesto', 'Guardafango delantero derecho', 1, 2800, $repuestera],
                    ['repuesto', 'Faro delantero derecho', 1, 1950, $repuestera],
                    ['tercero', 'Pintura de dos piezas y pulido', 1, 3200, $pintura],
                ],
            ],
            [
                'diagnostico' => 'Servicio mayor y detallado antes de publicar.',
                'lineas' => [
                    ['mano_obra', 'Servicio mayor: aceite, filtros y bujías', 4, null],
                    ['mano_obra', 'Revisión de frenos y suspensión', 3, null],
                    ['repuesto', 'Kit de filtros y aceite sintético', 1, 890, $repuestera],
                    ['repuesto', 'Juego de balatas delanteras', 1, 720, $repuestera],
                ],
            ],
        ];

        $enTaller = Unidad::where('estado', EstadoUnidad::EnTaller)->orderBy('id')->take(count($trabajos))->get();
        $abrir = app(AbrirOrdenTrabajo::class);
        $agregar = app(AgregarLineaOrdenTrabajo::class);

        foreach ($enTaller as $i => $unidad) {
            $trabajo = $trabajos[$i];

            $orden = $abrir->ejecutar($unidad, [
                'tipo' => 'preparacion',
                'jefe_id' => $jefe?->id,
                'estado' => 'en_proceso',
                'abierta_en' => now()->subDays(6 - $i * 2)->toDateString(),
                'diagnostico' => $trabajo['diagnostico'],
            ]);

            foreach ($trabajo['lineas'] as $j => $linea) {
                [$tipo, $descripcion, $cantidad, $costo] = $linea;
                $proveedor = $linea[4] ?? null;

                $agregar->ejecutar($orden, [
                    'tipo' => $tipo,
                    'descripcion' => $descripcion,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'empleado_id' => $tipo === 'mano_obra' ? $mecanicos[$j % $mecanicos->count()]->id : null,
                    'proveedor_id' => $proveedor?->id,
                    'estado' => $tipo === 'mano_obra' && $j === 0 ? 'hecha' : 'pendiente',
                ]);
            }
        }
    }
}
