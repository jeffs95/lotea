<?php

namespace App\Actions;

use App\Models\Venta;
use App\Support\Correlativo;

/** Correlativo de ventas por empresa: V-0001, V-0002... */
class GenerarNumeroVenta
{
    public function ejecutar(): string
    {
        return 'V-'.Correlativo::siguiente(Venta::query()->toBase(), 'numero');
    }
}
