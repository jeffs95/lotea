<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada persona que levanta la mano por un carro.
 *
 * El cronómetro de primera respuesta (`primera_respuesta_en`) es la métrica
 * que el dueño va a mirar: en este negocio el que contesta primero vende.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('nombre');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('mensaje')->nullable();

            $table->string('origen')->default('portal');   // portal | whatsapp | facebook | referido | mostrador
            $table->string('estado')->default('nuevo');    // nuevo | contactado | cotizado | visita | ganado | perdido
            $table->string('motivo_perdida')->nullable();

            $table->timestamp('primera_respuesta_en')->nullable();
            $table->timestamp('ultimo_contacto_en')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
