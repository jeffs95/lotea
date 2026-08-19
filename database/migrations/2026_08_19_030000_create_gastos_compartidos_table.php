<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un gasto que cubre varias unidades a la vez.
 *
 * El flete de un contenedor con 4 carros, o los honorarios del agente por una
 * póliza con 6 unidades. Hoy los concesionarios lo reparten a mano y mal; aquí
 * se registra una vez y el sistema lo distribuye con un criterio explícito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_compartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_costo_id')->constrained('categorias_costo');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            $table->string('descripcion');
            $table->date('fecha');

            // Moneda de origen + tipo de cambio del documento. Nunca se guarda
            // solo el monto convertido: el tipo de cambio del día que se pagó
            // es parte del hecho económico.
            $table->char('moneda', 3)->default('GTQ');
            $table->decimal('monto', 15, 2);
            $table->decimal('tipo_cambio', 12, 6)->default(1);
            $table->decimal('monto_base', 15, 2);

            $table->string('criterio')->default('partes_iguales'); // partes_iguales | por_valor
            $table->string('documento')->nullable();
            $table->boolean('es_presupuesto')->default(false);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos_compartidos');
    }
};
