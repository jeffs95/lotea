<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La unidad es el centro del sistema: un VIN con su propio estado de
 * resultados. Todo lo demás (costos, taller, venta) cuelga de aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            // Identidad
            $table->string('vin', 17);
            $table->string('stock_no', 20);
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('linea_id')->nullable()->constrained('lineas')->nullOnDelete();
            $table->string('version')->nullable();
            $table->unsignedSmallInteger('anio');

            // Ficha técnica
            $table->string('color')->nullable();
            $table->string('color_interior')->nullable();
            $table->string('carroceria')->nullable();       // sedán, SUV, pick-up, hatchback...
            $table->string('transmision')->nullable();      // automatica | manual
            $table->string('combustible')->nullable();      // gasolina | diesel | hibrido | electrico
            $table->string('traccion')->nullable();         // 4x2 | 4x4 | awd
            $table->string('motor')->nullable();
            $table->unsignedSmallInteger('cilindros')->nullable();
            $table->unsignedSmallInteger('puertas')->nullable();
            $table->unsignedInteger('odometro')->nullable();
            $table->string('odometro_unidad', 2)->default('mi');

            // Cómo viene de subasta: es lo que define el riesgo y el precio
            $table->string('tipo_titulo')->nullable();      // clean | salvage | rebuilt
            $table->string('tipo_dano')->nullable();
            $table->boolean('tiene_llaves')->default(true);

            // Ciclo de vida
            $table->string('estado')->default('comprada')->index();
            $table->timestamp('estado_desde')->nullable();  // para el aging por etapa
            $table->date('fecha_compra')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->date('fecha_lista')->nullable();
            $table->date('fecha_venta')->nullable();

            // Comercial
            $table->decimal('precio_lista', 15, 2)->nullable();
            $table->decimal('precio_minimo', 15, 2)->nullable();   // el piso que autoriza el dueño
            $table->boolean('publicado')->default(false);
            $table->boolean('destacado')->default(false);
            $table->string('slug')->nullable();
            $table->text('descripcion_comercial')->nullable();

            // Costo total: se recalcula al tocar los gastos. Vive aquí para no
            // sumar 20 filas cada vez que se pinta un listado.
            $table->decimal('costo_total', 15, 2)->default(0);
            $table->decimal('costo_presupuestado', 15, 2)->default(0);

            $table->string('ubicacion')->nullable();        // dónde está parado en el patio
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'vin']);
            $table->unique(['empresa_id', 'stock_no']);
            $table->unique(['empresa_id', 'slug']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'sucursal_id']);
            $table->index(['empresa_id', 'publicado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
