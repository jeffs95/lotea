<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quién se le vende.
 *
 * Sin esto, una unidad "vendida" no le pertenece a nadie: no hay a quién
 * cobrarle una cuota, a quién llamarle por la garantía, ni a quién volver a
 * venderle en tres años.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('tipo')->default('persona');     // persona | empresa
            $table->string('nombre');
            $table->string('nit')->nullable();
            $table->string('dpi')->nullable();
            $table->string('telefono')->nullable();
            $table->string('telefono_alterno')->nullable();
            $table->string('email')->nullable();
            $table->text('direccion')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'nombre']);
            $table->index(['empresa_id', 'nit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
