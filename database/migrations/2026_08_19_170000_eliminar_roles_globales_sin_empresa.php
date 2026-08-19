<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Borra los roles que quedaron sin empresa.
 *
 * Shield crea `super_admin` y `panel_user` como roles globales. Con nuestro
 * modelo eso es un agujero: un rol con todos los permisos y sin empresa ve los
 * datos de todos los concesionarios a la vez. El acceso de soporte de Lotea va
 * por users.es_operador, que ningún cliente puede darse a sí mismo.
 *
 * Los dos quedaron desactivados en config/filament-shield.php para que no
 * vuelvan a crearse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')->whereNull('empresa_id')->pluck('id');

        if ($roles->isEmpty()) {
            return;
        }

        DB::table('model_has_roles')->whereIn('role_id', $roles)->delete();
        DB::table('role_has_permissions')->whereIn('role_id', $roles)->delete();
        DB::table('roles')->whereIn('id', $roles)->delete();
    }

    public function down(): void
    {
        // No se restauran: eran el problema, no un dato que valga la pena.
    }
};
