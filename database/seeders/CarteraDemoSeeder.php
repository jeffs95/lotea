<?php

namespace Database\Seeders;

use App\Actions\GenerarPlanPago;
use App\Actions\RegistrarPagoCuota;
use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\PlanPago;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Un crédito propio al día y otro atrasado, que es como se ve una cartera de
 * verdad.
 */
class CarteraDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::firstWhere('slug', 'autos-del-valle'));
        }

        if (PlanPago::count() > 0) {
            return;
        }

        $vendedor = User::firstWhere('email', 'vendedor@lotea.test');
        $caja = Caja::activas()->where('moneda', 'GTQ')->first();

        $casos = [
            // cliente, meses atrás, plazo, tasa, cuotas ya pagadas
            [['nombre' => 'Edgar Sicán', 'nit' => '4455667-8', 'telefono' => '5588-9900'], 4, 24, 18, 4],
            [['nombre' => 'Verónica Alvarado', 'nit' => '7788990-1', 'telefono' => '4422-1100'], 5, 18, 20, 2],
        ];

        $disponibles = Unidad::whereIn('estado', [EstadoUnidad::Lista, EstadoUnidad::Publicada])
            ->orderByDesc('precio_lista')
            ->take(count($casos))
            ->get();

        $vender = app(RegistrarVenta::class);
        $financiar = app(GenerarPlanPago::class);
        $cobrar = app(RegistrarPagoCuota::class);

        foreach ($disponibles as $i => $unidad) {
            [$datosCliente, $mesesAtras, $plazo, $tasa, $pagadas] = $casos[$i];

            $cliente = Cliente::firstOrCreate(['nit' => $datosCliente['nit']], $datosCliente);
            $precio = (float) $unidad->precio_lista;
            $enganche = round($precio * 0.30, 2);

            $venta = $vender->ejecutar($unidad, [
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor?->id,
                'estado' => 'cerrada',
                'fecha' => now()->subMonths($mesesAtras)->toDateString(),
                'precio_venta' => $precio,
                'forma_pago' => 'credito_propio',
                'enganche' => $enganche,
                'saldo_financiado' => $precio - $enganche,
                'comision_base' => 'margen',
                'comision_porcentaje' => 4,
            ]);

            $plan = $financiar->ejecutar($venta, [
                'enganche' => $enganche,
                'plazo_meses' => $plazo,
                'tasa_anual' => $tasa,
                'tasa_mora_anual' => 36,
                'primera_cuota' => now()->subMonths($mesesAtras)->addMonth()->toDateString(),
                'gps_instalado' => true,
                'gps_referencia' => 'GPS-'.(4400 + $i),
            ]);

            foreach ($plan->cuotas()->take($pagadas)->get() as $cuota) {
                $cobrar->ejecutar($cuota, [
                    'monto' => $cuota->total,
                    'fecha' => $cuota->vence_en->toDateString(),
                    'metodo' => 'Depósito',
                ], $caja);
            }
        }
    }
}
