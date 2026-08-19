<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tope de lecturas al mes por plan: el add-on se paga por uso. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->unsignedInteger('max_lecturas_ia')->nullable()->after('max_unidades_activas');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn('max_lecturas_ia');
        });
    }
};
