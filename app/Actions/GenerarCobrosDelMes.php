<?php

namespace App\Actions;

use App\Models\Cobro;
use App\Models\Empresa;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Emite la mensualidad de todos los clientes que están operando.
 *
 * Es idempotente: correrla dos veces el mismo mes no duplica nada, porque el
 * par (empresa, periodo) es único. Así se puede automatizar sin miedo.
 */
class GenerarCobrosDelMes
{
    /** @return Collection<int, Cobro> */
    public function ejecutar(?CarbonInterface $mes = null): Collection
    {
        $mes ??= now();
        $periodo = $mes->format('Y-m');

        return DB::transaction(function () use ($mes, $periodo) {
            return Empresa::query()
                ->with('plan')
                ->where('activa', true)
                ->whereNull('suspendida_en')
                ->whereHas('plan', fn ($q) => $q->where('precio_mensual', '>', 0))
                ->get()
                ->map(fn (Empresa $empresa) => Cobro::firstOrCreate(
                    ['empresa_id' => $empresa->id, 'periodo' => $periodo],
                    [
                        'plan_id' => $empresa->plan_id,
                        'monto' => $empresa->plan->precio_mensual,
                        'concepto' => "Plan {$empresa->plan->nombre} · ".$mes->translatedFormat('F Y'),
                        'vence_en' => $mes->copy()->startOfMonth()->addDays(7)->toDateString(),
                        'estado' => 'pendiente',
                    ],
                ));
        });
    }
}
