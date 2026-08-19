<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un usuario pertenece a una o más empresas.
 *
 * Casi siempre será una sola; el pivote existe para el soporte técnico y para
 * el contador que atiende a dos concesionarios sin tener dos cuentas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono')->nullable()->after('email');
            $table->boolean('activo')->default(true)->after('telefono');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'activo', 'ultimo_acceso_at']);
        });

        Schema::dropIfExists('empresa_user');
    }
};
