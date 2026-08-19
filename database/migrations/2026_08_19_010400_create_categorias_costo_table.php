<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En qué se clasifica cada quetzal que se le mete a una unidad.
 *
 * Es el catálogo que hace legible la ficha de costo, así que cada cliente
 * recibe un juego base y puede ampliarlo. afecta_costo distingue lo que suma
 * al costo de la unidad (flete) de lo que no (comisión del vendedor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('nombre');
            $table->string('grupo');                              // compra | importacion | taller | venta | otros
            $table->boolean('afecta_costo')->default(true);       // ¿suma al costo de la unidad?
            $table->boolean('prorrateable')->default(false);      // ¿puede repartirse entre varias unidades?
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('es_sistema')->default(false);        // sembrada por Lotea, no se borra
            $table->boolean('activa')->default(true);

            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'grupo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_costo');
    }
};
