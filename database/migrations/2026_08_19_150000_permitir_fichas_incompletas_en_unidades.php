<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja pasar fichas incompletas.
 *
 * Al levantar el inventario de un patio se encuentran carros sin documentos a
 * la vista, y el recorrido no puede detenerse por eso: se captura lo que hay y
 * se cierra la ficha después. El VIN sigue siendo único cuando está, pero ya
 * no es obligatorio para que la unidad exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('vin', 17)->nullable()->change();
            $table->unsignedSmallInteger('anio')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('vin', 17)->nullable(false)->change();
            $table->unsignedSmallInteger('anio')->nullable(false)->change();
        });
    }
};
