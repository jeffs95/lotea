<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de estados de cada unidad.
 *
 * No es un log decorativo: de aquí salen los días por etapa, el aging y la
 * respuesta a "¿por qué este carro lleva 90 días en el patio?". Nunca se
 * edita ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_transiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->timestamp('ocurrio_en');
            $table->unsignedInteger('dias_en_estado_anterior')->nullable();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'unidad_id', 'ocurrio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_transiciones');
    }
};
