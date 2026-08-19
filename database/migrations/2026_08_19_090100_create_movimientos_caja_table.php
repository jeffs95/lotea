<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada entrada y salida de dinero.
 *
 * Igual que los costos: no se borra nada, se anula con motivo. Una caja donde
 * se pueden borrar movimientos no sirve para cuadrar con nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();

            $table->string('tipo');                       // ingreso | egreso
            $table->string('categoria')->default('otro'); // venta | enganche | cuota | gasto | traslado | aporte | retiro | otro

            $table->date('fecha');
            $table->string('descripcion');

            $table->char('moneda', 3)->default('GTQ');
            $table->decimal('monto', 15, 2);
            $table->decimal('tipo_cambio', 12, 6)->default(1);
            $table->decimal('monto_base', 15, 2);

            $table->string('referencia')->nullable();     // boleta, cheque, recibo
            $table->string('documento')->nullable();

            // De dónde vino: una venta, una cuota, un gasto de unidad.
            $table->nullableMorphs('origen');

            // Las dos patas de un traslado se apuntan entre sí.
            $table->foreignId('contraparte_id')->nullable()->constrained('movimientos_caja')->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'caja_id', 'fecha']);
            $table->index(['empresa_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
