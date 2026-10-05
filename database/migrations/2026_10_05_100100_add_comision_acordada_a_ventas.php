<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El monto pactado para esta venta en concreto.
 *
 * Se guarda aparte de «comision_monto», que es el resultado del cálculo: aquí
 * queda lo que se acordó y allá lo que se terminó pagando. Con una sola
 * columna no se podría saber después si un monto raro fue un acuerdo especial
 * o una cuenta mal hecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $tabla) {
            $tabla->decimal('comision_acordada', 15, 2)->nullable()->after('comision_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $tabla) {
            $tabla->dropColumn('comision_acordada');
        });
    }
};
