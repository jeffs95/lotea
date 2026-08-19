<?php

namespace App\Actions;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mueve dinero de una caja a otra.
 *
 * Son dos movimientos, no uno: sale de un lado y entra al otro, y quedan
 * apuntándose entre sí para que anular uno anule los dos.
 */
class TrasladarEntreCajas
{
    public function __construct(private RegistrarMovimientoCaja $registrar) {}

    /** @return Collection<int, MovimientoCaja> */
    public function ejecutar(Caja $origen, Caja $destino, array $datos): Collection
    {
        if ($origen->is($destino)) {
            throw new DomainException('El origen y el destino no pueden ser la misma caja.');
        }

        if ($origen->moneda !== $destino->moneda) {
            throw new DomainException(
                'Las dos cajas tienen que ser de la misma moneda. Un cambio de dólares a quetzales '
                .'se registra como dos movimientos con su tipo de cambio.'
            );
        }

        $monto = (string) $datos['monto'];

        if (bccomp((string) $origen->saldo, $monto, 2) < 0) {
            throw new DomainException("La caja «{$origen->nombre}» no tiene saldo suficiente.");
        }

        return DB::transaction(function () use ($origen, $destino, $datos, $monto) {
            $descripcion = $datos['descripcion'] ?? "Traslado de {$origen->nombre} a {$destino->nombre}";

            $salida = $this->registrar->ejecutar($origen, [
                'tipo' => 'egreso',
                'categoria' => 'traslado',
                'monto' => $monto,
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'descripcion' => $descripcion,
                'referencia' => $datos['referencia'] ?? null,
            ]);

            $entrada = $this->registrar->ejecutar($destino, [
                'tipo' => 'ingreso',
                'categoria' => 'traslado',
                'monto' => $monto,
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'descripcion' => $descripcion,
                'referencia' => $datos['referencia'] ?? null,
            ]);

            $salida->update(['contraparte_id' => $entrada->id]);
            $entrada->update(['contraparte_id' => $salida->id]);

            return collect([$salida->refresh(), $entrada->refresh()]);
        });
    }
}
