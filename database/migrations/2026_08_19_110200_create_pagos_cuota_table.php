<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada abono que hace el cliente.
 *
 * Separado de la cuota porque un cliente puede abonar en partes, y porque el
 * recibo que se le entrega es del pago, no de la cuota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_cuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimientos_caja')->nullOnDelete();

            $table->string('recibo');
            $table->date('fecha');
            $table->decimal('monto', 15, 2);
            $table->decimal('mora', 15, 2)->default(0);
            $table->string('metodo')->nullable();
            $table->string('referencia')->nullable();
            $table->text('notas')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_cuota');
    }
};
