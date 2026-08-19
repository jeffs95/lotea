<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién puede entrar al panel central.
 *
 * Es una bandera aparte y no un rol de Shield a propósito: los roles viven por
 * empresa y se editan desde el panel del cliente. Si el acceso al panel del
 * proveedor dependiera de un rol, un cliente podría dárselo a sí mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_operador')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('es_operador');
        });
    }
};
