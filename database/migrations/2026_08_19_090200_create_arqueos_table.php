<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El conteo físico contra lo que dice el sistema.
 *
 * La diferencia se guarda y se justifica; no se "ajusta" el saldo por detrás.
 * Una caja que cuadra siempre es una caja que nadie revisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('realizado_en');
            $table->decimal('saldo_sistema', 15, 2);
            $table->decimal('saldo_contado', 15, 2);
            $table->decimal('diferencia', 15, 2);
            $table->text('justificacion')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'caja_id', 'realizado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqueos');
    }
};
