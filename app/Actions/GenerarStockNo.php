<?php

namespace App\Actions;

use App\Models\Unidad;
use App\Support\Tenancy;

/**
 * El número de stock es como el dueño y los vendedores llaman al carro en
 * WhatsApp: corto, correlativo y por empresa. No es el VIN.
 */
class GenerarStockNo
{
    public function ejecutar(?string $prefijo = null): string
    {
        $ultimo = Unidad::withTrashed()
            ->where('empresa_id', Tenancy::empresaId())
            ->max('id');

        $correlativo = str_pad((string) (($ultimo ?? 0) + 1), 4, '0', STR_PAD_LEFT);

        return $prefijo ? "{$prefijo}-{$correlativo}" : $correlativo;
    }
}
