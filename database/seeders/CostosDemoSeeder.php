<?php

namespace Database\Seeders;

use App\Actions\RegistrarCosto;
use App\Actions\RegistrarGastoCompartido;
use App\Enums\EstadoUnidad;
use App\Models\CategoriaCosto;
use App\Models\CostoUnidad;
use App\Models\Proveedor;
use App\Models\TipoCambio;
use App\Models\Unidad;
use Illuminate\Database\Seeder;

/**
 * Le pone costos creíbles a la flota de demostración.
 *
 * La estructura es la real de una importación de subasta a Guatemala: martillo
 * y fees en dólares, impuestos y taller en quetzales, y el flete marítimo
 * repartido entre los carros que venían en el mismo contenedor.
 */
class CostosDemoSeeder extends Seeder
{
    protected const TIPO_CAMBIO = 7.70;

    public function run(): void
    {
        TipoCambio::firstOrCreate(
            ['fecha' => now()->toDateString(), 'moneda' => 'USD'],
            ['tasa' => self::TIPO_CAMBIO, 'fuente' => 'manual'],
        );

        $categorias = CategoriaCosto::pluck('id', 'codigo');
        $registrar = app(RegistrarCosto::class);

        $subasta = Proveedor::firstOrCreate(
            ['tipo' => 'subasta', 'nombre' => 'Copart Houston'],
            ['pais' => 'US', 'moneda_default' => 'USD'],
        );

        $agente = Proveedor::firstOrCreate(
            ['tipo' => 'agente_aduanal', 'nombre' => 'Aduanas del Istmo'],
            ['pais' => 'GT', 'moneda_default' => 'GTQ'],
        );

        $unidades = Unidad::orderBy('id')->get();

        foreach ($unidades as $i => $unidad) {
            $precio = (float) $unidad->precio_lista;
            $fecha = $unidad->fecha_compra;

            // Martillo: entre 27% y 33% del precio de venta, en dólares.
            $martillo = round(($precio * (0.27 + (($i % 4) * 0.02))) / self::TIPO_CAMBIO, 0);

            $enDolares = [
                ['precio_compra', $martillo, $subasta->id, 'Lote '.(4200000 + $i * 137)],
                ['fees_subasta', round($martillo * 0.15, 0), $subasta->id, null],
                ['transporte_usa', 350 + ($i % 3) * 60, null, null],
            ];

            foreach ($enDolares as [$codigo, $monto, $proveedorId, $documento]) {
                $registrar->ejecutar($unidad, [
                    'categoria_costo_id' => $categorias[$codigo],
                    'proveedor_id' => $proveedorId,
                    'monto' => $monto,
                    'moneda' => 'USD',
                    'tipo_cambio' => self::TIPO_CAMBIO,
                    'fecha' => $fecha,
                    'documento' => $documento,
                ]);
            }

            // Impuestos y trámite local, en quetzales. Solo para las que ya
            // pasaron aduana: un carro que va en el barco todavía no pagó.
            if ($this->yaPasoAduana($unidad)) {
                $cif = $martillo * self::TIPO_CAMBIO;

                $enQuetzales = [
                    ['iprima', round($cif * 0.20, 2), null, 'DUA '.(2026000 + $i)],
                    ['dai', round($cif * 0.05, 2), null, null],
                    ['iva_importacion', round($cif * 0.12, 2), null, null],
                    ['honorarios_agente', 1800, $agente->id, null],
                    ['transporte_local', 750 + ($i % 3) * 150, null, null],
                ];

                foreach ($enQuetzales as [$codigo, $monto, $proveedorId, $documento]) {
                    $registrar->ejecutar($unidad, [
                        'categoria_costo_id' => $categorias[$codigo],
                        'proveedor_id' => $proveedorId,
                        'monto' => $monto,
                        'fecha' => $fecha?->copy()->addDays(35),
                        'documento' => $documento,
                    ]);
                }
            }

            // Taller: solo las que ya llegaron al patio.
            if ($this->yaEntroAlTaller($unidad)) {
                $registrar->ejecutar($unidad, [
                    'categoria_costo_id' => $categorias['repuestos'],
                    'monto' => round($precio * (0.05 + (($i % 5) * 0.012)), 2),
                    'fecha' => $fecha?->copy()->addDays(50),
                    'descripcion' => 'Repuestos de la orden de trabajo',
                ]);

                $registrar->ejecutar($unidad, [
                    'categoria_costo_id' => $categorias['mano_obra'],
                    'monto' => round($precio * 0.03, 2),
                    'fecha' => $fecha?->copy()->addDays(50),
                    'descripcion' => 'Mano de obra',
                ]);
            }
        }

        $this->fleteDelContenedor($unidades, $categorias);
        $this->presupuestosDelComprador($unidades, $categorias);
    }

    /**
     * Lo que el comprador estimó antes de pujar en la subasta.
     *
     * Se guarda con las mismas categorías que el gasto real, marcado como
     * presupuesto: así la ficha de rentabilidad puede enfrentar las dos
     * columnas y decir en qué se pasó.
     */
    protected function presupuestosDelComprador($unidades, $categorias): void
    {
        $registrar = app(RegistrarCosto::class);

        // Estimaciones típicas del comprador: el flete y los impuestos casi
        // siempre se quedan cortos, y el taller se subestima aún más.
        $factores = [
            'precio_compra' => 1.00,
            'fees_subasta' => 0.85,
            'transporte_usa' => 0.90,
            'flete_maritimo' => 0.88,
            'iprima' => 0.95,
            'honorarios_agente' => 1.00,
            'repuestos' => 0.70,
            'mano_obra' => 0.80,
        ];

        foreach ($unidades as $unidad) {
            $reales = CostoUnidad::where('unidad_id', $unidad->id)
                ->vigentes()
                ->reales()
                ->with('categoria')
                ->get()
                ->groupBy(fn (CostoUnidad $c) => $c->categoria->codigo);

            foreach ($factores as $codigo => $factor) {
                $real = (float) ($reales->get($codigo)?->sum('monto_base') ?? 0);

                if ($real <= 0) {
                    continue;
                }

                $registrar->ejecutar($unidad, [
                    'categoria_costo_id' => $categorias[$codigo],
                    'monto' => round($real * $factor, 2),
                    'fecha' => $unidad->fecha_compra,
                    'es_presupuesto' => true,
                    'descripcion' => 'Estimado antes de la subasta',
                ]);
            }
        }
    }

    /**
     * Un contenedor de 40 pies con cuatro carros adentro: el caso que hoy los
     * concesionarios reparten a mano en una servilleta.
     */
    protected function fleteDelContenedor($unidades, $categorias): void
    {
        $delContenedor = $unidades->filter(fn (Unidad $u) => $this->yaPasoAduana($u))->take(4);

        if ($delContenedor->count() < 2) {
            return;
        }

        app(RegistrarGastoCompartido::class)->ejecutar([
            'categoria_costo_id' => $categorias['flete_maritimo'],
            'descripcion' => 'Flete marítimo contenedor 40\' MSCU7841203',
            'monto' => 4400,
            'moneda' => 'USD',
            'tipo_cambio' => self::TIPO_CAMBIO,
            'criterio' => 'partes_iguales',
            'documento' => 'BL MEDUGT884120',
            'fecha' => now()->subDays(40)->toDateString(),
        ], $delContenedor);
    }

    protected function yaPasoAduana(Unidad $unidad): bool
    {
        return in_array($unidad->estado->etapa(), ['preparacion', 'venta', 'cerrada'], true);
    }

    protected function yaEntroAlTaller(Unidad $unidad): bool
    {
        return in_array($unidad->estado, [
            EstadoUnidad::EnTaller, EstadoUnidad::Lista, EstadoUnidad::Publicada,
            EstadoUnidad::Reservada, EstadoUnidad::Vendida, EstadoUnidad::Entregada,
        ], true);
    }
}
