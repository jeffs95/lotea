<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El precio rebajado de una unidad que lleva mucho en el patio.
 *
 * Un carro parado cuesta dinero todos los días: capital detenido, patio
 * ocupado, y el modelo envejeciendo. A las dos o tres semanas la pregunta deja
 * de ser cuánto vale y pasa a ser cuánto hay que bajarle para que salga.
 *
 * El precio de lista NO se toca. Se guarda aparte el de oferta, y así el portal
 * puede enseñar los dos: el de antes tachado y el de ahora. Esa comparación es
 * lo que vende; cambiar el precio de lista a secas no la permite, y además
 * perdería el dato de a cuánto se había puesto.
 *
 * La vigencia es opcional: hay quien liquida con fecha y quien solo quiere
 * bajar el precio hasta que salga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $tabla) {
            $tabla->decimal('precio_oferta', 15, 2)->nullable()->after('precio_minimo');
            $tabla->date('oferta_hasta')->nullable()->after('precio_oferta');
            $tabla->string('oferta_etiqueta', 40)->nullable()->after('oferta_hasta');

            // Para listar las ofertas vigentes del portal sin recorrer todo.
            $tabla->index(['empresa_id', 'precio_oferta']);
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $tabla) {
            $tabla->dropIndex(['empresa_id', 'precio_oferta']);
            $tabla->dropColumn(['precio_oferta', 'oferta_hasta', 'oferta_etiqueta']);
        });
    }
};
