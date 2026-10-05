<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el concesionario le paga a cada vendedor por carro vendido.
 *
 * Hasta ahora la comisión era siempre un porcentaje, del margen o del precio.
 * Eso sirve para un vendedor de planta, pero no para como se trabaja aquí: hay
 * gente de fuera del negocio que publica los carros en sus redes y cobra un
 * monto acordado por cada uno que sale, sin importar en cuánto se vendió.
 *
 * Va en la tabla que une al usuario con la empresa y no en el usuario: lo
 * acordado es entre ese vendedor y ese concesionario. La misma persona podría
 * vender para dos y haber pactado distinto con cada uno.
 *
 * Es un valor de arranque, no una atadura: en cada venta se puede escribir otro
 * monto, porque algunos carros se negocian aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_user', function (Blueprint $tabla) {
            $tabla->decimal('comision_por_venta', 15, 2)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_user', function (Blueprint $tabla) {
            $tabla->dropColumn('comision_por_venta');
        });
    }
};
