<?php

namespace App\Actions;

use App\Models\OrdenTrabajo;

/**
 * Vuelve a sumar los totales de una orden.
 *
 * Corre cada vez que se toca una línea. Es la única que escribe esos totales.
 */
class RecalcularOrdenTrabajo
{
    public function ejecutar(OrdenTrabajo $orden): OrdenTrabajo
    {
        $porTipo = $orden->lineas()
            ->selectRaw('tipo, sum(total) as suma')
            ->groupBy('tipo')
            ->pluck('suma', 'tipo');

        $orden->forceFill([
            'total_mano_obra' => $porTipo['mano_obra'] ?? 0,
            'total_repuestos' => $porTipo['repuesto'] ?? 0,
            'total_terceros' => $porTipo['tercero'] ?? 0,
        ])->save();

        return $orden->refresh();
    }
}
