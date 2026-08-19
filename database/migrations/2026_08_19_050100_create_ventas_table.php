<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cierre del ciclo: el precio real al que salió el carro.
 *
 * Hasta aquí la rentabilidad trabajaba con el precio de lista, que es una
 * aspiración. La venta trae el número de verdad, con su descuento y su
 * comisión, y con eso el margen deja de ser un estimado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            $table->string('numero');                        // correlativo por empresa
            $table->string('estado')->default('cotizacion'); // cotizacion | reservada | cerrada | anulada
            $table->date('fecha');

            // Reserva: el apartado con depósito y fecha de vencimiento.
            $table->decimal('deposito', 15, 2)->nullable();
            $table->date('deposito_vence_en')->nullable();

            // El dinero de verdad
            $table->decimal('precio_venta', 15, 2);          // lo pactado antes de descuento
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('precio_final', 15, 2);          // lo que realmente paga

            $table->string('forma_pago')->default('contado'); // contado | financiamiento_banco | credito_propio | mixto
            $table->decimal('enganche', 15, 2)->nullable();
            $table->decimal('saldo_financiado', 15, 2)->nullable();

            // Comisión: se calcula sobre el margen real, no sobre el precio.
            // Un vendedor premiado por precio regala margen sin darse cuenta.
            $table->string('comision_base')->default('margen'); // margen | precio
            $table->decimal('comision_porcentaje', 6, 3)->default(0);
            $table->decimal('comision_monto', 15, 2)->default(0);
            $table->boolean('comision_pagada')->default(false);

            // Factura emitida por fuera: FEL quedó fuera de alcance.
            $table->string('factura_serie')->nullable();
            $table->string('factura_numero')->nullable();
            $table->string('factura_uuid')->nullable();
            $table->date('factura_fecha')->nullable();

            $table->date('entregada_en')->nullable();
            $table->text('notas')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulada_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
