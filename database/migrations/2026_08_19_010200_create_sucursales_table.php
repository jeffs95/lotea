<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Patios de una misma empresa. Separación blanda: el dueño ve todas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('nombre');
            $table->text('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('encargado')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('activa')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
