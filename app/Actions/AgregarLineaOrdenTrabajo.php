<?php

namespace App\Actions;

use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\OtLinea;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mete una línea a la orden y recalcula.
 *
 * Para la mano de obra, si no se indica el costo por hora se toma el del
 * mecánico: así el jefe de taller solo apunta las horas y no tiene que saber
 * cuánto gana cada quien.
 */
class AgregarLineaOrdenTrabajo
{
    public function __construct(private RecalcularOrdenTrabajo $recalcular) {}

    public function ejecutar(OrdenTrabajo $orden, array $datos): OtLinea
    {
        if (! $orden->admiteCambios()) {
            throw new DomainException("La orden {$orden->numero} ya está cerrada y no admite cambios.");
        }

        $cantidad = (string) ($datos['cantidad'] ?? 1);
        $costoUnitario = (string) ($datos['costo_unitario'] ?? $this->costoPorDefecto($datos));

        if (bccomp($cantidad, '0.00', 2) <= 0) {
            throw new DomainException('La cantidad tiene que ser mayor que cero.');
        }

        return DB::transaction(function () use ($orden, $datos, $cantidad, $costoUnitario) {
            $linea = OtLinea::create([
                'orden_trabajo_id' => $orden->id,
                'tipo' => $datos['tipo'],
                'descripcion' => $datos['descripcion'],
                'empleado_id' => $datos['empleado_id'] ?? null,
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'total' => bcmul($cantidad, $costoUnitario, 2),
                'estado' => $datos['estado'] ?? 'pendiente',
                'documento' => $datos['documento'] ?? null,
                'notas' => $datos['notas'] ?? null,
            ]);

            $this->recalcular->ejecutar($orden);

            return $linea;
        });
    }

    /** El costo por hora del mecánico asignado, si lo tiene. */
    protected function costoPorDefecto(array $datos): string
    {
        if (($datos['tipo'] ?? null) !== 'mano_obra' || blank($datos['empleado_id'] ?? null)) {
            return '0';
        }

        return (string) (Empleado::find($datos['empleado_id'])?->costo_hora ?? 0);
    }
}
