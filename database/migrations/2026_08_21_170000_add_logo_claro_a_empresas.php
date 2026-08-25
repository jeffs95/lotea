<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El logo en versión para fondos claros.
 *
 * Faltaba, y por eso la cabecera del portal —que es blanca— pintaba el archivo
 * original del cliente con su fondo negro pegado: se veía un recuadro oscuro en
 * medio de una página clara.
 *
 * Con los tres campos queda cubierto: el original es lo que el cliente subió y
 * no se toca, y de él salen la versión clara y la oscura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('logo_claro_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('logo_claro_path');
        });
    }
};
