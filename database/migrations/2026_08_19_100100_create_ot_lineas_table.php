<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El detalle de una orden: mano de obra, repuestos y trabajos a terceros.
 *
 * Una sola tabla con tipo en vez de tres: las tres se suman igual y así el
 * cálculo del total de la orden es una consulta y no tres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('orden_trabajo_id')->constrained('ordenes_trabajo')->cascadeOnDelete();

            $table->string('tipo');  // mano_obra | repuesto | tercero
            $table->string('descripcion');

            // Mano de obra: el mecánico y sus horas. Repuesto o tercero: el proveedor.
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            $table->decimal('cantidad', 10, 2)->default(1);      // horas o unidades
            $table->decimal('costo_unitario', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->string('estado')->default('pendiente');      // pendiente | hecha
            $table->string('documento')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'orden_trabajo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_lineas');
    }
};
