<?php

namespace Database\Seeders;

use App\Actions\AltaDeConcesionario;
use App\Actions\GenerarCobrosDelMes;
use App\Actions\SuspenderConcesionario;
use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Un par de clientes más para que el panel central se vea como se verá de
 * verdad: uno al día, uno suspendido por falta de pago.
 */
class ConcesionariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $este = $this->alta(
            ['nombre' => 'Importadora del Sur, S.A.', 'nombre_comercial' => 'Importadora del Sur', 'slug' => 'importadora-del-sur',
             'nit' => '9988776-5', 'telefono' => '7767-1122', 'email' => 'info@importadoradelsur.gt',
             'contacto_nombre' => 'Ana Pérez', 'contacto_telefono' => '5566-7788',
             'sucursal_principal' => 'Patio Xela'],
            ['name' => 'Ana Pérez', 'email' => 'ana@importadoradelsur.gt', 'password' => 'password'],
            'pro',
        );

        $moroso = $this->alta(
            ['nombre' => 'Vehículos del Norte', 'nombre_comercial' => 'Autos del Norte', 'slug' => 'autos-del-norte',
             'nit' => '5544332-1', 'telefono' => '7930-4455',
             'contacto_nombre' => 'Luis Barrientos', 'contacto_telefono' => '4411-2233',
             'notas_internas' => 'Paga tarde todos los meses. Hay que llamarle el día 5.',
             'sucursal_principal' => 'Patio Cobán'],
            ['name' => 'Luis Barrientos', 'email' => 'luis@autosdelnorte.gt', 'password' => 'password'],
            'basico',
        );

        if ($moroso && ! $moroso->estaSuspendida()) {
            app(SuspenderConcesionario::class)->suspender($moroso, 'Mensualidad de julio y agosto sin pagar');
        }

        // Un par de meses de historia para que los cobros no salgan vacíos.
        $cobros = app(GenerarCobrosDelMes::class);
        $cobros->ejecutar();

        $cobros->ejecutar(now()->subMonth())->each(fn (Cobro $cobro) => $cobro->update([
            'estado' => 'pagado',
            'pagado_en' => $cobro->vence_en,
            'metodo_pago' => 'Transferencia',
            'referencia' => 'TRF-'.str_pad((string) $cobro->id, 5, '0', STR_PAD_LEFT),
        ]));
    }

    protected function alta(array $empresa, array $dueno, string $planSlug): ?Empresa
    {
        if ($existente = Empresa::firstWhere('slug', $empresa['slug'])) {
            return $existente;
        }

        return app(AltaDeConcesionario::class)->ejecutar(
            $empresa,
            $dueno,
            Plan::firstWhere('slug', $planSlug),
        );
    }
}
