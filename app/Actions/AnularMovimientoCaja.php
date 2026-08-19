<?php

namespace App\Actions;

use App\Models\MovimientoCaja;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Anula un movimiento y, si era un traslado, también su contraparte.
 *
 * Anular solo una de las dos patas dejaría dinero apareciendo o
 * desapareciendo entre cajas.
 */
class AnularMovimientoCaja
{
    public function ejecutar(MovimientoCaja $movimiento, string $motivo): MovimientoCaja
    {
        if ($movimiento->estaAnulado()) {
            throw new DomainException('Este movimiento ya estaba anulado.');
        }

        if (blank(trim($motivo))) {
            throw new DomainException('Hay que decir por qué se anula el movimiento.');
        }

        return DB::transaction(function () use ($movimiento, $motivo) {
            $anulacion = [
                'anulado_en' => now(),
                'anulado_por' => Auth::id(),
                'motivo_anulacion' => trim($motivo),
            ];

            $movimiento->update($anulacion);

            if ($movimiento->contraparte && ! $movimiento->contraparte->estaAnulado()) {
                $movimiento->contraparte->update($anulacion);
            }

            return $movimiento->refresh();
        });
    }
}
