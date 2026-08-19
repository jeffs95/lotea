<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los planes que vende Lotea.
 *
 * Los límites viven en la base y no en el código, porque el día que haya que
 * hacerle una excepción a un cliente grande no se puede depender de un deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->text('descripcion')->nullable();

            $table->decimal('precio_mensual', 12, 2)->default(0);

            // null = sin límite
            $table->unsignedInteger('max_sucursales')->nullable();
            $table->unsignedInteger('max_usuarios')->nullable();
            $table->unsignedInteger('max_unidades_activas')->nullable();

            $table->json('modulos')->nullable();   // qué módulos habilita
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
