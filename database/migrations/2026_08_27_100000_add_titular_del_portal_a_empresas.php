<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El titular de la portada, en manos de cada concesionario.
 *
 * Era un texto fijo en la plantilla, igual para todos: hablaba de traer las
 * unidades de subasta y prepararlas en el taller. Está bien contado pero habla
 * del proceso, y quien entra a la página quiere ver el carro y su precio.
 *
 * Y sobre todo: ese es el mensaje de venta de cada patio, no de Lotea. Uno
 * vende motos de trabajo, otro camionetas de lujo, y no les sirve la misma
 * frase. Con esto lo cambia cada uno desde su panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->string('titular_portal', 120)->nullable()->after('portada_path');
            $tabla->string('subtitulo_portal', 300)->nullable()->after('titular_portal');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $tabla) {
            $tabla->dropColumn(['titular_portal', 'subtitulo_portal']);
        });
    }
};
