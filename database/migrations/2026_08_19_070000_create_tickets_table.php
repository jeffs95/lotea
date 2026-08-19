<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los problemas que reportan los clientes.
 *
 * El contexto se captura solo. La diferencia entre "no me sirve" y "vendedor
 * Carlos, pantalla de unidades, le falta el permiso de crear" es media hora de
 * soporte por caso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('numero');
            $table->string('asunto');
            $table->text('mensaje');

            $table->json('contexto')->nullable();   // rol, pantalla, navegador
            $table->string('estado')->default('abierto');  // abierto | en_proceso | resuelto

            $table->text('respuesta')->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('respondido_en')->nullable();

            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
