<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de cada recurso, página y widget del panel (ViewAny:Unidad y
 * compañía).
 *
 * Sin esto una instalación nueva arranca con tres permisos y el primer cliente
 * que se da de alta recibe un rol de dueño casi vacío: entra a su panel y no
 * ve nada. Corre aquí para que el despliegue sea un solo comando.
 *
 * Se piden sólo los permisos, nunca las políticas: las de este proyecto están
 * escritas a mano y el generador las sobreescribiría.
 */
class PermisosDeShieldSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
