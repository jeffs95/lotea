<?php

use App\Models\Unidad;
use App\Support\CodigoDeUnidad;
use App\Support\Tenancy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El código que va impreso en el parabrisas.
 *
 * Es único en todo el sistema y no por empresa, porque la ruta del QR resuelve
 * la unidad antes de saber de qué concesionario es. Corto y sin caracteres que
 * se confundan al dictarlo por teléfono.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('codigo_qr', 12)->nullable()->unique()->after('stock_no');
        });

        // Las unidades que ya existían también necesitan el suyo.
        Tenancy::sinFiltro(function () {
            Unidad::withTrashed()->whereNull('codigo_qr')->cursor()->each(
                fn (Unidad $unidad) => $unidad->forceFill(['codigo_qr' => CodigoDeUnidad::generar()])->saveQuietly()
            );
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn('codigo_qr');
        });
    }
};
