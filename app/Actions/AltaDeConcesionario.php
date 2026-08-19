<?php

namespace App\Actions;

use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Da de alta un concesionario completo desde el panel central.
 *
 * CrearEmpresa deja la empresa operable por dentro (sucursal, categorías y
 * roles); esto agrega lo que hace falta para que alguien pueda entrar a
 * usarla: el usuario dueño con todos los permisos y el primer cobro.
 */
class AltaDeConcesionario
{
    public function __construct(private CrearEmpresa $crearEmpresa) {}

    public function ejecutar(array $datosEmpresa, array $datosDueno, ?Plan $plan = null): Empresa
    {
        // El nombre de la sucursal viaja en el mismo formulario pero no es un
        // campo de la empresa: se saca antes de tocar el modelo.
        $sucursal = $datosEmpresa['sucursal_principal'] ?? 'Casa matriz';
        unset($datosEmpresa['sucursal_principal']);

        return DB::transaction(function () use ($datosEmpresa, $datosDueno, $plan, $sucursal) {
            $empresa = $this->crearEmpresa->ejecutar(
                [...$datosEmpresa, 'plan_id' => $plan?->id],
                $sucursal,
            );

            $dueno = User::firstOrCreate(
                ['email' => $datosDueno['email']],
                [
                    'name' => $datosDueno['name'],
                    'password' => $datosDueno['password'] ?? Str::password(12),
                    'telefono' => $datosDueno['telefono'] ?? null,
                    'activo' => true,
                ],
            );

            $dueno->empresas()->syncWithoutDetaching([$empresa->id]);

            Tenancy::comoEmpresa($empresa, function () use ($dueno) {
                // El dueño puede todo dentro de su empresa. Los permisos ya
                // existen porque los genera Shield; aquí solo se asignan.
                Role::findOrCreate('dueno', 'web')->syncPermissions(Permission::all());
                $dueno->assignRole('dueno');
            });

            if ($plan && $plan->precio_mensual > 0) {
                $this->primerCobro($empresa, $plan);
            }

            return $empresa->refresh();
        });
    }

    /** El cobro del mes en que entra, con vencimiento a 8 días. */
    protected function primerCobro(Empresa $empresa, Plan $plan): void
    {
        Cobro::firstOrCreate(
            ['empresa_id' => $empresa->id, 'periodo' => now()->format('Y-m')],
            [
                'plan_id' => $plan->id,
                'monto' => $plan->precio_mensual,
                'concepto' => "Plan {$plan->nombre} · ".now()->translatedFormat('F Y'),
                'vence_en' => now()->addDays(8)->toDateString(),
                'estado' => 'pendiente',
            ],
        );
    }
}
