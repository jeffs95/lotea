<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ocho decimales en lugar de seis.
 *
 * Una lectura cuesta unas ocho diezmilésimas de dólar; con seis decimales el
 * redondeo ya se come parte del dato y las sumas del mes salen corridas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas_ia', function (Blueprint $table) {
            $table->decimal('costo_usd', 14, 8)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('lecturas_ia', function (Blueprint $table) {
            $table->decimal('costo_usd', 12, 6)->default(0)->change();
        });
    }
};
