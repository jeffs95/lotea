<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quita la columna `plan` de texto que quedó de antes de que existieran los
 * planes de verdad.
 *
 * No es solo limpieza: el atributo tapaba a la relación plan(), así que
 * $empresa->plan devolvía la cadena 'pro' en lugar del modelo, y cualquier
 * $empresa->plan->precio_mensual reventaba.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En una instalación nueva la columna ya no se crea; esta migración
        // existe solo para los entornos que la tenían.
        if (! Schema::hasColumn('empresas', 'plan')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }

    public function down(): void
    {
        // No se restaura: la columna era el bug, no un dato que valga la pena.
    }
};
