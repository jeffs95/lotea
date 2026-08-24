<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hace falta para que el portal diga dónde está el patio y cómo
 * escribirle al concesionario.
 *
 * Las coordenadas van con seis decimales: son ~11 cm de precisión, de sobra
 * para llevar a alguien a la entrada de un patio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->string('horario', 120)->nullable()->after('longitud');
            $table->string('whatsapp', 30)->nullable()->after('telefono');
            $table->boolean('mostrar_en_portal')->default(true)->after('activa');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->string('whatsapp', 30)->nullable()->after('telefono');
            $table->string('facebook', 200)->nullable()->after('whatsapp');
            $table->string('instagram', 200)->nullable()->after('facebook');
            $table->string('tiktok', 200)->nullable()->after('instagram');
            $table->string('youtube', 200)->nullable()->after('tiktok');
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'horario', 'whatsapp', 'mostrar_en_portal']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'facebook', 'instagram', 'tiktok', 'youtube']);
        });
    }
};
