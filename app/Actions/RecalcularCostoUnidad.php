<?php

namespace App\Actions;

use App\Models\CostoUnidad;
use App\Models\Unidad;

/**
 * Vuelve a sumar el costo de una unidad y lo guarda en la ficha.
 *
 * El total vive en `unidades` a propósito: sumar 20 filas cada vez que se
 * pinta un listado de 200 carros no escala. Esta acción es la única que
 * escribe ese número, y corre cada vez que un costo entra, cambia o se anula.
 */
class RecalcularCostoUnidad
{
    public function ejecutar(Unidad $unidad): Unidad
    {
        $base = CostoUnidad::query()
            ->where('unidad_id', $unidad->id)
            ->vigentes()
            ->queAfectanCosto();

        $real = (clone $base)->reales()->sum('monto_base');
        $presupuesto = (clone $base)->presupuestados()->sum('monto_base');

        $unidad->forceFill([
            'costo_total' => $real,
            'costo_presupuestado' => $presupuesto,
        ])->save();

        return $unidad;
    }
}
