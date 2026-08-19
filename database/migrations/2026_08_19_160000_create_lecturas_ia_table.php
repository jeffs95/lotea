<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada lectura de documentos que hace un cliente, con lo que costó.
 *
 * Sin esto el add-on se vende a ciegas: no se sabe cuánto consume cada cliente
 * ni si el precio que se le cobra deja margen. Los tokens los reporta
 * OpenRouter en cada respuesta, así que el costo es el real y no un estimado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturas_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('modelo');
            $table->unsignedSmallInteger('documentos')->default(1);
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);

            // En dólares con seis decimales: una lectura cuesta milésimas.
            $table->decimal('costo_usd', 12, 6)->default(0);

            $table->unsignedSmallInteger('campos_leidos')->default(0);
            $table->boolean('exitosa')->default(true);
            $table->string('error')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturas_ia');
    }
};
