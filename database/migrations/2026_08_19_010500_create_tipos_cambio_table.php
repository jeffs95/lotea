<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cambio de referencia, global para todos los tenants (lo publica el
 * Banguat, no lo inventa cada concesionario).
 *
 * Ojo: esto es la referencia diaria. El tipo de cambio con el que se registra
 * un gasto vive en el documento mismo, porque el que compra dólares en la
 * calle no usa el del banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->char('moneda', 3);                    // moneda origen; destino siempre GTQ
            $table->decimal('tasa', 12, 6);
            $table->string('fuente')->default('banguat'); // banguat | manual
            $table->timestamps();

            $table->unique(['fecha', 'moneda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
