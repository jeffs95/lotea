<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada quetzal que se le mete a un carro.
 *
 * Es la tabla más importante del sistema: de aquí sale el costo real, la
 * utilidad y la respuesta a "¿qué me conviene comprar la próxima vez?".
 *
 * Dos reglas que no se negocian:
 *   1. Nada se borra. Se anula con motivo, usuario y fecha.
 *   2. Todo lleva moneda de origen y tipo de cambio, no solo el convertido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_unidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('categoria_costo_id')->constrained('categorias_costo');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            $table->string('descripcion')->nullable();
            $table->date('fecha');

            $table->char('moneda', 3)->default('GTQ');
            $table->decimal('monto', 15, 2);
            $table->decimal('tipo_cambio', 12, 6)->default(1);
            $table->decimal('monto_base', 15, 2);

            // Presupuestado vs real: el comprador estima el landed cost antes
            // de pujar en la subasta y después compara contra lo que pasó.
            $table->boolean('es_presupuesto')->default(false);

            // Si vino de repartir un gasto compartido, de cuál.
            $table->foreignId('prorrateado_de_id')->nullable()
                ->constrained('gastos_compartidos')->cascadeOnDelete();

            $table->string('documento')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'unidad_id', 'es_presupuesto']);
            $table->index(['empresa_id', 'categoria_costo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_unidad');
    }
};
