<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Lo que necesita una instalación de Lotea para arrancar, y nada más.
 *
 * Aquí no se siembran concesionarios ni vehículos: los clientes se dan de alta
 * desde el panel central y cada uno mete su propio inventario. Esto es lo que
 * corre en producción tal cual.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Los permisos de cada recurso del panel: sin esto el primer
            // cliente recibe un rol de dueño vacío.
            PermisosDeShieldSeeder::class,

            // Y los que no salen de un recurso de Filament.
            PermisosPropiosSeeder::class,

            // Marcas y líneas compartidas por todos los clientes.
            CatalogosSeeder::class,

            // Los planes que se venden.
            PlanesSeeder::class,

            // La cuenta de Lotea para entrar al panel central.
            OperadorSeeder::class,
        ]);
    }
}
