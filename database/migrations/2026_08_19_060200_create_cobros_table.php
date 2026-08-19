<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La mensualidad de cada cliente, mes a mes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();

            $table->string('periodo', 7);                 // 2026-08
            $table->decimal('monto', 12, 2);
            $table->string('concepto')->nullable();

            $table->date('vence_en');
            $table->string('estado')->default('pendiente'); // pendiente | pagado | vencido | condonado

            $table->date('pagado_en')->nullable();
            $table->string('metodo_pago')->nullable();
            $table->string('referencia')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'periodo']);
            $table->index(['estado', 'vence_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};
