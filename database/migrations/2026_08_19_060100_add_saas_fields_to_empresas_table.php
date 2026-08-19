<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lo que Lotea necesita saber de cada cliente para cobrarle y atenderlo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('moneda_base')->constrained('planes')->nullOnDelete();

            // Suspendida ≠ inactiva: la suspensión es por falta de pago y se
            // levanta sola cuando pagan. Se guarda aparte para no perder el
            // motivo ni confundirla con una baja definitiva.
            $table->timestamp('suspendida_en')->nullable()->after('activa');
            $table->string('motivo_suspension')->nullable()->after('suspendida_en');

            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_telefono')->nullable();
            $table->text('notas_internas')->nullable();   // para vos, no para el cliente
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn([
                'plan_id', 'suspendida_en', 'motivo_suspension',
                'contacto_nombre', 'contacto_telefono', 'notas_internas',
            ]);
        });
    }
};
