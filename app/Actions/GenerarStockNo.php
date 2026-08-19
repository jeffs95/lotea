<?php

namespace App\Actions;

use App\Models\Unidad;
use App\Support\Correlativo;

/**
 * El número de stock es como el dueño y los vendedores llaman al carro en
 * WhatsApp: corto, correlativo y por empresa. No es el VIN.
 *
 * Cuenta también las unidades borradas: hay un unique (empresa_id, stock_no)
 * en la base, así que reciclar el número de una unidad de la papelera
 * reventaría el alta.
 */
class GenerarStockNo
{
    public function ejecutar(?string $prefijo = null): string
    {
        $correlativo = Correlativo::siguiente(
            Unidad::withTrashed()->toBase(),
            'stock_no',
        );

        return $prefijo ? "{$prefijo}-{$correlativo}" : $correlativo;
    }
}
