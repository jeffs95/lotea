<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dónde está el dinero: la caja chica de cada patio y las cuentas de banco.
 *
 * Las cuentas en dólares son necesarias de verdad: la subasta y la naviera se
 * pagan en dólares, y mezclarlas con los quetzales esconde el diferencial
 * cambiario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            $table->string('nombre');
            $table->string('tipo')->default('efectivo');   // efectivo | banco
            $table->char('moneda', 3)->default('GTQ');

            $table->string('banco')->nullable();
            $table->string('numero_cuenta')->nullable();

            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'sucursal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
