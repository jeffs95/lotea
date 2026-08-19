<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se le hace a un carro en el taller.
 *
 * Es donde se decide el margen: el taller es el costo más variable de la
 * unidad y el que más se subestima al pujar en la subasta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('jefe_id')->nullable()->constrained('empleados')->nullOnDelete();

            $table->string('numero');
            $table->string('tipo')->default('preparacion');  // preparacion | reparacion | garantia | otro
            $table->string('estado')->default('abierta');    // abierta | en_proceso | terminada | cerrada | anulada

            $table->date('abierta_en');
            $table->date('terminada_en')->nullable();
            $table->timestamp('cerrada_en')->nullable();

            $table->text('diagnostico')->nullable();
            $table->text('notas')->nullable();

            // Totales por tipo de línea. Se recalculan al tocar las líneas.
            $table->decimal('total_mano_obra', 15, 2)->default(0);
            $table->decimal('total_repuestos', 15, 2)->default(0);
            $table->decimal('total_terceros', 15, 2)->default(0);

            // Al cerrar, los totales pasan a costos_unidad. La bandera evita
            // que se dupliquen si alguien vuelve a cerrar la orden.
            $table->boolean('costos_descargados')->default(false);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'unidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
