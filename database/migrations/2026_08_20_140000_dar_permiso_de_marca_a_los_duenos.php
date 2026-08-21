<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * El permiso de marca para los concesionarios que ya existían.
 *
 * Al dar de alta una empresa, el rol «dueño» recibe todos los permisos que
 * haya en ese momento; los que ya estaban creados se quedarían sin este. Se
 * escribe directo en la tabla pivote para no depender del contexto de empresa
 * activa, que en una migración no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permisoId = Permission::findOrCreate('administrar_marca', 'web')->getKey();

        $duenos = DB::table('roles')->where('name', 'dueno')->pluck('id');

        foreach ($duenos as $rolId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permisoId,
                'role_id' => $rolId,
            ]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permissions')->where('name', 'administrar_marca')->value('id');

        if ($permisoId) {
            DB::table('role_has_permissions')->where('permission_id', $permisoId)->delete();
        }
    }
};
