<?php

namespace Database\Seeders;

use App\Actions\RegistrarMovimientoCaja;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\TipoCambio;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/** Las cajas del concesionario de demostración, con algo de movimiento. */
class CajasDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::firstWhere('slug', 'autos-del-valle'));
        }

        if (Caja::count() > 0) {
            return;
        }

        TipoCambio::firstOrCreate(
            ['fecha' => now()->toDateString(), 'moneda' => 'USD'],
            ['tasa' => 7.70, 'fuente' => 'manual'],
        );

        $sucursal = Sucursal::first();

        $chica = Caja::create([
            'sucursal_id' => $sucursal?->id,
            'nombre' => 'Caja chica Roosevelt',
            'tipo' => 'efectivo',
            'moneda' => 'GTQ',
            'saldo_inicial' => 5000,
        ]);

        $banco = Caja::create([
            'nombre' => 'Banrural monetaria',
            'tipo' => 'banco',
            'moneda' => 'GTQ',
            'banco' => 'Banrural',
            'numero_cuenta' => '3-456-78901-2',
            'saldo_inicial' => 180000,
        ]);

        // La cuenta en dólares es la que paga subastas y navieras.
        Caja::create([
            'nombre' => 'Cuenta dólares',
            'tipo' => 'banco',
            'moneda' => 'USD',
            'banco' => 'Banco Industrial',
            'numero_cuenta' => '045-9988776',
            'saldo_inicial' => 12000,
        ]);

        $registrar = app(RegistrarMovimientoCaja::class);

        $movimientos = [
            [$banco, 'ingreso', 'venta', 95060, 'Cobro venta V-0001 · María Elena López', 'TRF-77120'],
            [$banco, 'ingreso', 'venta', 78310, 'Cobro venta V-0002 · Transportes San Marcos', 'TRF-77145'],
            [$chica, 'egreso', 'gasto', 1800, 'Honorarios agente aduanal', 'REC-0912'],
            [$chica, 'egreso', 'gasto', 750, 'Grúa de aduana al patio', 'REC-0913'],
            [$chica, 'ingreso', 'aporte', 3000, 'Aporte del dueño para caja chica', null],
        ];

        foreach ($movimientos as $i => [$caja, $tipo, $categoria, $monto, $descripcion, $referencia]) {
            $registrar->ejecutar($caja, [
                'tipo' => $tipo,
                'categoria' => $categoria,
                'monto' => $monto,
                'fecha' => now()->subDays(12 - $i * 2)->toDateString(),
                'descripcion' => $descripcion,
                'referencia' => $referencia,
            ]);
        }
    }
}
