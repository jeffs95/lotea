<?php

namespace App\Actions;

use App\Models\CostoUnidad;
use App\Models\OrdenTrabajo;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Anula una orden y le quita a la unidad los costos que le había cargado.
 *
 * Si no se anularan esos costos, el carro quedaría cargando el trabajo de una
 * orden que no existió y su margen saldría bajo sin explicación.
 */
class AnularOrdenTrabajo
{
    public function __construct(private AnularCosto $anularCosto) {}

    public function ejecutar(OrdenTrabajo $orden, string $motivo): OrdenTrabajo
    {
        if ($orden->estaAnulada()) {
            throw new DomainException('Esta orden ya estaba anulada.');
        }

        if (blank(trim($motivo))) {
            throw new DomainException('Hay que decir por qué se anula la orden.');
        }

        return DB::transaction(function () use ($orden, $motivo) {
            CostoUnidad::where('unidad_id', $orden->unidad_id)
                ->where('documento', $orden->numero)
                ->vigentes()
                ->get()
                ->each(fn (CostoUnidad $costo) => $this->anularCosto->ejecutar(
                    $costo,
                    "Orden {$orden->numero} anulada: {$motivo}",
                ));

            $orden->update([
                'estado' => 'anulada',
                'motivo_anulacion' => trim($motivo),
                'costos_descargados' => false,
            ]);

            return $orden->refresh();
        });
    }
}
