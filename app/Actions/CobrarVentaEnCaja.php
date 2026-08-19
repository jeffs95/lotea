<?php

namespace App\Actions;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use DomainException;

/**
 * Mete a la caja lo que el cliente pagó por un carro.
 *
 * El movimiento queda enlazado a la venta, así que después se puede ver de
 * dónde salió cada quetzal sin adivinar por la descripción.
 */
class CobrarVentaEnCaja
{
    public function __construct(private RegistrarMovimientoCaja $registrar) {}

    public function ejecutar(Venta $venta, Caja $caja, array $datos): MovimientoCaja
    {
        if ($venta->estaAnulada()) {
            throw new DomainException('No se puede cobrar una venta anulada.');
        }

        $esEnganche = ($datos['categoria'] ?? null) === 'enganche';

        return $this->registrar->ejecutar($caja, [
            'tipo' => 'ingreso',
            'categoria' => $datos['categoria'] ?? 'venta',
            'monto' => $datos['monto'],
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'descripcion' => $datos['descripcion']
                ?? ($esEnganche ? "Enganche venta {$venta->numero}" : "Cobro venta {$venta->numero}")
                   ." · {$venta->cliente->nombre}",
            'referencia' => $datos['referencia'] ?? null,
            'documento' => $venta->numero,
        ], $venta);
    }
}
