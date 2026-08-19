<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Permisos que no salen de un recurso de Filament.
 *
 * El de costos es el más importante del sistema: el vendedor no debe ver
 * cuánto costó la unidad. Si eso se filtra, el dueño pierde su margen en la
 * negociación. Es requisito de negocio, no un detalle técnico.
 */
class PermisosPropiosSeeder extends Seeder
{
    public const PERMISOS = [
        'ver_costos_unidad' => 'Ver el costo y la utilidad de las unidades',
        'ver_precio_minimo' => 'Ver el precio mínimo autorizado',
    ];

    public function run(): void
    {
        foreach (array_keys(self::PERMISOS) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
