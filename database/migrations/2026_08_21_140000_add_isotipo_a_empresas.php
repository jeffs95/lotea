<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El símbolo del logo, sin el nombre escrito.
 *
 * Es el que va en el centro del QR del parabrisas y en la pestaña del
 * navegador: en un cuadro de 22 milímetros el nombre no se lee, y meterlo solo
 * gasta espacio. Se saca del logo con lotea:variantes-logo, pero el cliente
 * puede subir el suyo si el diseñador ya se lo dio aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('isotipo_path')->nullable()->after('logo_oscuro_path');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('isotipo_path');
        });
    }
};
