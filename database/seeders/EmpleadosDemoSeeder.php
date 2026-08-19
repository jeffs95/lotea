<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/** La planilla del concesionario de demostración. */
class EmpleadosDemoSeeder extends Seeder
{
    public const PLANILLA = [
        // código, nombres, apellidos, puesto, área, salario, costo hora, mecánico
        ['EMP-001', 'Carlos', 'Ramírez', 'Vendedor', 'ventas', 4500, null, false],
        ['EMP-002', 'Lucía', 'Del Cid', 'Vendedora', 'ventas', 4500, null, false],
        ['EMP-003', 'Mario', 'Xoyón', 'Mecánico automotriz', 'taller', 5200, 45, true],
        ['EMP-004', 'Byron', 'Estrada', 'Enderezado y pintura', 'taller', 4800, 42, true],
        ['EMP-005', 'Josué', 'Pirir', 'Jefe de taller', 'taller', 7500, 65, true],
        ['EMP-006', 'Andrea', 'Godínez', 'Cajera', 'administracion', 4000, null, false],
        ['EMP-007', 'Silvia', 'Morales', 'Contadora', 'administracion', 8000, null, false],
    ];

    public function run(): void
    {
        if (! Tenancy::hayEmpresa()) {
            Tenancy::usar(Empresa::firstWhere('slug', 'autos-del-valle'));
        }

        $sucursal = Sucursal::first();
        $vendedor = User::firstWhere('email', 'vendedor@lotea.test');

        foreach (self::PLANILLA as $i => [$codigo, $nombres, $apellidos, $puesto, $area, $salario, $costoHora, $esMecanico]) {
            Empleado::firstOrCreate(['codigo' => $codigo], [
                'sucursal_id' => $sucursal?->id,
                // Carlos es además el usuario vendedor del sistema.
                'user_id' => $codigo === 'EMP-001' ? $vendedor?->id : null,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'puesto' => $puesto,
                'area' => $area,
                'tipo_contrato' => 'indefinido',
                'fecha_ingreso' => now()->subMonths(6 + $i * 4),
                'salario_base' => $salario,
                'bonificacion_incentivo' => 250,
                'costo_hora' => $costoHora,
                'es_mecanico' => $esMecanico,
                'telefono' => '5'.str_pad((string) (500000 + $i * 1111), 7, '0', STR_PAD_LEFT),
                'activo' => true,
            ]);
        }
    }
}
