<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La imagen de fondo de la portada del portal.
 *
 * Donde hoy hay un degradado con el color de la marca, el concesionario puede
 * poner una foto de su patio o de un carro. Encima se mantiene una capa oscura:
 * sin ella el titular blanco se pierde sobre una foto clara y la portada queda
 * ilegible, que es peor que no tener foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('portada_path')->nullable()->after('isotipo_path');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('portada_path');
        });
    }
};
