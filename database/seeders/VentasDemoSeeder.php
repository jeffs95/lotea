<?php

namespace Database\Seeders;

use App\Actions\RegistrarVenta;
use App\Enums\EstadoUnidad;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Role;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Cierra las ventas de las unidades que en la demo ya están entregadas, para
 * que la rentabilidad de esas muestre margen real y no estimado.
 */
class VentasDemoSeeder extends Seeder
{
    /**
     * Un vendedor de verdad para poder demostrar la regla más importante del
     * sistema: entra, ve el inventario y sus ventas, y no ve un solo costo.
     */
    protected function vendedorDeDemostracion(): User
    {
        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@lotea.test'],
            ['name' => 'Carlos Ramírez', 'password' => 'password', 'activo' => true],
        );

        $empresa = Empresa::findOrFail(Tenancy::empresaId());
        $vendedor->empresas()->syncWithoutDetaching([$empresa->id]);

        $rol = Role::findOrCreate('vendedor', 'web');

        // Todo lo que necesita para trabajar, menos los permisos de dinero.
        $rol->syncPermissions(
            Permission::where('name', 'not ilike', '%costo%')
                ->whereIn('name', [
                    'ViewAny:Unidad', 'View:Unidad',
                    'ViewAny:Venta', 'View:Venta', 'Create:Venta', 'Update:Venta',
                    'ViewAny:Cliente', 'View:Cliente', 'Create:Cliente', 'Update:Cliente',
                    'ViewAny:Lead', 'View:Lead', 'Create:Lead', 'Update:Lead',
                    'View:TableroUnidades',
                ])
                ->get()
        );

        $vendedor->assignRole($rol);

        return $vendedor;
    }

    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::first());
        }

        $vendedor = $this->vendedorDeDemostracion();

        $clientes = collect([
            ['nombre' => 'María Elena López', 'nit' => '2345678-9', 'telefono' => '5544-1122'],
            ['nombre' => 'Transportes San Marcos, S.A.', 'nit' => '8765432-1', 'telefono' => '2233-4455', 'tipo' => 'empresa'],
            ['nombre' => 'Rodrigo Menchú', 'nit' => '3456789-0', 'telefono' => '4477-8899'],
        ])->map(fn (array $datos) => Cliente::firstOrCreate(['nit' => $datos['nit']], $datos));

        $vendidas = Unidad::where('estado', EstadoUnidad::Entregada)->orderBy('id')->get();
        $registrar = app(RegistrarVenta::class);

        foreach ($vendidas as $i => $unidad) {
            if ($unidad->venta()->exists()) {
                continue;
            }

            // Se cierra un poco por debajo del precio de lista, como pasa siempre.
            $descuento = round((float) $unidad->precio_lista * (0.03 + ($i % 3) * 0.015), 2);

            $registrar->ejecutar($unidad, [
                'cliente_id' => $clientes[$i % $clientes->count()]->id,
                'vendedor_id' => $vendedor->id,
                'estado' => 'cerrada',
                'fecha' => $unidad->fecha_venta ?? now()->subDays(10),
                'precio_venta' => $unidad->precio_lista,
                'descuento' => $descuento,
                'forma_pago' => $i % 2 === 0 ? 'contado' : 'financiamiento_banco',
                'comision_base' => 'margen',
                'comision_porcentaje' => 5,
                'factura_serie' => 'A',
                'factura_numero' => (string) (1200 + $i),
            ]);
        }
    }
}
