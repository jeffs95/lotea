<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El permiso de ver las ventas de los demás, y quién lo trae de fábrica.
 *
 * Va en una migración y no solo en el seeder porque el seeder de permisos no
 * se corre en cada despliegue, y aquí el descuido no se nota: sin el permiso
 * en la base nadie lo tiene, y «nadie» incluye al dueño, que abriría la
 * pantalla de ventas y vería únicamente las suyas. Un permiso que no existe
 * recorta a todo el mundo.
 */
return new class extends Migration
{
    /** Los que necesitan el cuadro completo para pagar, cobrar o cuadrar. */
    protected const ROLES = ['dueno', 'gerente_sucursal', 'cajero', 'contador'];

    public function up(): void
    {
        $permiso = DB::table('permissions')
            ->where('name', 'ver_ventas_ajenas')
            ->where('guard_name', 'web')
            ->value('id');

        $permiso ??= DB::table('permissions')->insertGetId([
            'name' => 'ver_ventas_ajenas',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Por empresa, porque los roles lo son: cada concesionario tiene su
        // propio «dueno» y el permiso se le da al suyo, no a uno compartido.
        $roles = DB::table('roles')
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roles as $rol) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permiso,
                'role_id' => $rol,
            ]);
        }

        $this->olvidarElCache();
    }

    public function down(): void
    {
        $permiso = DB::table('permissions')
            ->where('name', 'ver_ventas_ajenas')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permiso === null) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permiso)->delete();
        DB::table('permissions')->where('id', $permiso)->delete();

        $this->olvidarElCache();
    }

    /**
     * Spatie guarda los permisos en caché un día entero.
     *
     * Sin esto la migración deja la base correcta y el sistema siguiéndose
     * creyendo lo de antes: el dueño entra, no tiene el permiso que acaba de
     * recibir y ve solo sus propias ventas. Pasó en desarrollo tal cual.
     */
    protected function olvidarElCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
