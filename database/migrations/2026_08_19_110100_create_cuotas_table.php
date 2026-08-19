<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla de amortización: qué debe pagar el cliente y cuándo.
 *
 * El capital y el interés van separados desde el día uno. Un plan que solo
 * guarda "cuota de Q2,500" no sabe cuánto le queda de deuda real a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('plan_pago_id')->constrained('planes_pago')->cascadeOnDelete();

            $table->unsignedSmallInteger('numero');
            $table->date('vence_en');

            $table->decimal('capital', 15, 2);
            $table->decimal('interes', 15, 2);
            $table->decimal('total', 15, 2);
            $table->decimal('saldo_despues', 15, 2);      // lo que queda debiendo tras pagarla

            $table->decimal('pagado', 15, 2)->default(0);
            $table->decimal('mora_cobrada', 15, 2)->default(0);
            $table->date('pagada_en')->nullable();

            $table->string('estado')->default('pendiente'); // pendiente | parcial | pagada

            $table->timestamps();

            $table->unique(['plan_pago_id', 'numero']);
            $table->index(['empresa_id', 'estado', 'vence_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
