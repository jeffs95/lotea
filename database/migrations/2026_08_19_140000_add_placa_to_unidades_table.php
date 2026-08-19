<?php

use App\Enums\TipoPlaca;
use App\Models\Unidad;
use App\Support\Tenancy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * La placa y su tipo.
 *
 * Nullable a propósito: una unidad que viene de subasta no tiene placa
 * guatemalteca hasta que se nacionaliza, y hasta entonces el campo queda
 * vacío sin que eso sea un error.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('placa', 20)->nullable()->after('vin');
            $table->string('tipo_placa', 4)->nullable()->after('placa');
        });

        Schema::table('unidades', function (Blueprint $table) {
            $table->index(['empresa_id', 'placa']);
        });

        $this->recuperarLasQueQuedaronEnNotas();
    }

    /**
     * Antes de que existiera el campo, el lector de documentos guardaba la
     * placa dentro de las notas. Aquí se rescata de ahí.
     */
    protected function recuperarLasQueQuedaronEnNotas(): void
    {
        Tenancy::sinFiltro(function () {
            Unidad::withTrashed()
                ->whereNull('placa')
                ->where('notas', 'like', '%Placa según documento:%')
                ->cursor()
                ->each(function (Unidad $unidad) {
                    if (! preg_match('/Placa según documento:\s*([A-Za-z0-9-]+)/u', (string) $unidad->notas, $coincidencias)) {
                        return;
                    }

                    $placa = Str::upper($coincidencias[1]);

                    $unidad->forceFill([
                        'placa' => $placa,
                        'tipo_placa' => TipoPlaca::desdeLaPlaca($placa)?->value,
                        'notas' => Str::of($unidad->notas)
                            ->replaceMatches('/Placa según documento:\s*[A-Za-z0-9-]+\.?\s*/u', '')
                            ->trim()
                            ->value() ?: null,
                    ])->saveQuietly();
                });
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'placa']);
            $table->dropColumn(['placa', 'tipo_placa']);
        });
    }
};
