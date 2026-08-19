<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crédito propio: cuando el concesionario financia él mismo el carro.
 *
 * Es lo que más amarra al cliente con el sistema, porque la cartera es plata
 * que se cobra mes a mes durante años y no puede vivir en un cuaderno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');

            $table->string('numero');
            $table->date('fecha');

            $table->decimal('precio_venta', 15, 2);
            $table->decimal('enganche', 15, 2)->default(0);
            $table->decimal('monto_financiado', 15, 2);

            $table->decimal('tasa_anual', 6, 3)->default(0);       // % nominal anual
            $table->decimal('tasa_mora_anual', 6, 3)->default(0);  // % sobre lo vencido
            $table->unsignedSmallInteger('plazo_meses');
            $table->decimal('cuota_mensual', 15, 2);
            $table->date('primera_cuota');

            $table->string('estado')->default('vigente');  // vigente | cancelado | recuperado | anulado

            $table->boolean('gps_instalado')->default(false);
            $table->string('gps_referencia')->nullable();
            $table->text('notas')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_pago');
    }
};
