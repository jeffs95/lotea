<?php

namespace App\Actions;

use App\Enums\EstadoUnidad;
use App\Models\CostoUnidad;
use App\Models\Venta;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Anula una venta y deshace lo que dejó a su paso.
 *
 * La unidad vuelve al patio y la comisión se anula, porque una venta caída no
 * puede seguir cargándole un gasto al carro ni contando como vendida.
 */
class AnularVenta
{
    public function __construct(
        private AnularCosto $anularCosto,
        private CambiarEstadoUnidad $cambiarEstado,
    ) {}

    public function ejecutar(Venta $venta, string $motivo): Venta
    {
        if ($venta->estaAnulada()) {
            throw new DomainException('Esta venta ya estaba anulada.');
        }

        if (blank(trim($motivo))) {
            throw new DomainException('Hay que decir por qué se anula la venta.');
        }

        return DB::transaction(function () use ($venta, $motivo) {
            $venta->update([
                'estado' => 'anulada',
                'anulada_en' => now(),
                'anulada_por' => Auth::id(),
                'motivo_anulacion' => trim($motivo),
            ]);

            // La comisión se anula con su propio rastro, no se borra.
            CostoUnidad::where('unidad_id', $venta->unidad_id)
                ->where('documento', $venta->numero)
                ->vigentes()
                ->get()
                ->each(fn (CostoUnidad $costo) => $this->anularCosto->ejecutar(
                    $costo,
                    "Venta {$venta->numero} anulada: {$motivo}",
                ));

            // Vuelve al escaparate si estaba dada por vendida.
            $unidad = $venta->unidad;

            if ($unidad->estado === EstadoUnidad::Vendida) {
                $unidad->forceFill([
                    'estado' => EstadoUnidad::Lista,
                    'estado_desde' => now(),
                    'fecha_venta' => null,
                ])->save();

                $unidad->transiciones()->create([
                    'estado_anterior' => EstadoUnidad::Vendida,
                    'estado_nuevo' => EstadoUnidad::Lista,
                    'ocurrio_en' => now(),
                    'user_id' => Auth::id(),
                    'nota' => "Venta {$venta->numero} anulada: {$motivo}",
                ]);
            }

            return $venta->refresh();
        });
    }
}
