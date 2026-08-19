<?php

namespace App\Actions;

use App\Models\Venta;
use App\Support\Tenancy;

/** Correlativo de ventas por empresa: V-0001, V-0002... */
class GenerarNumeroVenta
{
    public function ejecutar(): string
    {
        $ultimo = Venta::where('empresa_id', Tenancy::empresaId())->max('id');

        return 'V-'.str_pad((string) (($ultimo ?? 0) + 1), 4, '0', STR_PAD_LEFT);
    }
}
