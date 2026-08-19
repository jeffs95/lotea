<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue automóvil de motocicleta y de pesado.
 *
 * Lo que ya existía son automóviles: es lo único que se registraba hasta hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('tipo_vehiculo')->default('automovil')->after('linea_id');

            // En moto es el dato que primero pregunta el cliente; en auto,
            // uno más de la ficha.
            $table->unsignedSmallInteger('cilindrada_cc')->nullable()->after('cilindros');
        });

        Schema::table('unidades', function (Blueprint $table) {
            $table->index(['empresa_id', 'tipo_vehiculo']);
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'tipo_vehiculo']);
            $table->dropColumn(['tipo_vehiculo', 'cilindrada_cc']);
        });
    }
};
