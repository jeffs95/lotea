<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca blanca por concesionario.
 *
 * logo_path y color_primario ya existían (los usa el portal). Faltaba el logo
 * para fondo oscuro —el panel tiene modo oscuro y un logo oscuro ahí se
 * pierde— y el favicon, que es lo que el cliente ve en la pestaña del
 * navegador todo el día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('logo_oscuro_path')->nullable()->after('logo_path');
            $table->string('favicon_path')->nullable()->after('logo_oscuro_path');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['logo_oscuro_path', 'favicon_path']);
        });
    }
};
