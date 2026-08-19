<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La gente del concesionario.
 *
 * Separado de `users` a propósito: el mecánico del taller casi nunca tiene
 * usuario del sistema, y el contador que entra desde afuera no es empleado.
 * Cuando una persona es las dos cosas, se enlazan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('codigo');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dpi')->nullable();
            $table->string('nit')->nullable();
            $table->string('igss_afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();

            $table->string('puesto');
            $table->string('area')->default('administracion'); // ventas | taller | administracion | gerencia
            $table->string('tipo_contrato')->default('indefinido');
            $table->date('fecha_ingreso');
            $table->date('fecha_baja')->nullable();
            $table->string('motivo_baja')->nullable();

            $table->decimal('salario_base', 12, 2)->default(0);
            $table->decimal('bonificacion_incentivo', 12, 2)->default(250); // Decreto 78-89

            // Lo que cuesta una hora suya. De aquí sale la mano de obra que el
            // taller le carga a cada unidad.
            $table->decimal('costo_hora', 10, 2)->nullable();
            $table->boolean('es_mecanico')->default(false);

            $table->string('banco')->nullable();
            $table->string('cuenta_banco')->nullable();

            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->text('direccion')->nullable();
            $table->text('notas')->nullable();

            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'area']);
            $table->index(['empresa_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
