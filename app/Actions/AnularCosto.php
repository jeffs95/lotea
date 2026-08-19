<?php

namespace App\Actions;

use App\Models\CostoUnidad;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * En dinero no se borra: se anula.
 *
 * Queda el registro, el motivo y quién lo hizo. Así el dueño puede auditar qué
 * pasó, y un gasto "desaparecido" no puede usarse para maquillar un margen.
 */
class AnularCosto
{
    public function __construct(private RecalcularCostoUnidad $recalcular) {}

    public function ejecutar(CostoUnidad $costo, string $motivo): CostoUnidad
    {
        if ($costo->estaAnulado()) {
            throw new DomainException('Este gasto ya estaba anulado.');
        }

        if (blank(trim($motivo))) {
            throw new DomainException('Hay que decir por qué se anula el gasto.');
        }

        return DB::transaction(function () use ($costo, $motivo) {
            $costo->update([
                'anulado_en' => now(),
                'anulado_por' => Auth::id(),
                'motivo_anulacion' => trim($motivo),
            ]);

            $this->recalcular->ejecutar($costo->unidad);

            return $costo->refresh();
        });
    }
}
