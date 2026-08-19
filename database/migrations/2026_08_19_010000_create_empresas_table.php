<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La empresa es el tenant: un concesionario cliente de Lotea. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();

            // Identidad
            $table->string('nombre');                        // razón social
            $table->string('nombre_comercial')->nullable();
            $table->string('nit')->nullable();
            $table->string('slug')->unique();                // ruta del panel: /app/{slug}
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('direccion')->nullable();

            // Portal público propio
            $table->string('dominio')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('color_primario')->default('#f59e0b');

            // Operación
            $table->char('moneda_base', 3)->default('GTQ');

            // Suscripción SaaS (el plan se enlaza en una migración posterior)
            $table->boolean('activa')->default(true);
            $table->date('fecha_activacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
