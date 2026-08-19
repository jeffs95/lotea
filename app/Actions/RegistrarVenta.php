<?php

namespace App\Actions;

use App\Enums\EstadoUnidad;
use App\Models\CategoriaCosto;
use App\Models\Unidad;
use App\Models\Venta;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registra una cotización, una reserva o una venta cerrada.
 *
 * Cuando la venta se cierra pasan tres cosas a la vez: la unidad cambia de
 * estado, se calcula la comisión y esa comisión entra como gasto de la unidad.
 * Las tres o ninguna, por eso van en una transacción.
 */
class RegistrarVenta
{
    public function __construct(
        private CambiarEstadoUnidad $cambiarEstado,
        private RegistrarCosto $registrarCosto,
    ) {}

    public function ejecutar(Unidad $unidad, array $datos): Venta
    {
        if ($unidad->venta()->exists()) {
            throw new DomainException("La unidad {$unidad->stock_no} ya tiene una venta cerrada.");
        }

        return DB::transaction(function () use ($unidad, $datos) {
            $precioVenta = (string) $datos['precio_venta'];
            $descuento = (string) ($datos['descuento'] ?? 0);
            $precioFinal = bcsub($precioVenta, $descuento, 2);

            $venta = Venta::create([
                'unidad_id' => $unidad->id,
                'cliente_id' => $datos['cliente_id'],
                'vendedor_id' => $datos['vendedor_id'] ?? null,
                'sucursal_id' => $datos['sucursal_id'] ?? $unidad->sucursal_id,
                'numero' => $datos['numero'] ?? app(GenerarNumeroVenta::class)->ejecutar(),
                'estado' => $datos['estado'] ?? 'cotizacion',
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'deposito' => $datos['deposito'] ?? null,
                'deposito_vence_en' => $datos['deposito_vence_en'] ?? null,
                'precio_venta' => $precioVenta,
                'descuento' => $descuento,
                'precio_final' => $precioFinal,
                'forma_pago' => $datos['forma_pago'] ?? 'contado',
                'enganche' => $datos['enganche'] ?? null,
                'saldo_financiado' => $datos['saldo_financiado'] ?? null,
                'comision_base' => $datos['comision_base'] ?? 'margen',
                'comision_porcentaje' => $datos['comision_porcentaje'] ?? 0,
                'comision_monto' => 0,
                'factura_serie' => $datos['factura_serie'] ?? null,
                'factura_numero' => $datos['factura_numero'] ?? null,
                'factura_uuid' => $datos['factura_uuid'] ?? null,
                'factura_fecha' => $datos['factura_fecha'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'user_id' => Auth::id(),
            ]);

            if ($venta->estado === 'cerrada') {
                $this->cerrar($venta, $unidad);
            }

            return $venta->refresh();
        });
    }

    /** Aplica el cierre: comisión, gasto y cambio de estado de la unidad. */
    public function cerrar(Venta $venta, ?Unidad $unidad = null): Venta
    {
        $unidad ??= $venta->unidad;

        return DB::transaction(function () use ($venta, $unidad) {
            $comision = $this->calcularComision($venta, $unidad);

            $venta->update([
                'estado' => 'cerrada',
                'comision_monto' => $comision,
            ]);

            if (bccomp($comision, '0.00', 2) > 0 && $venta->vendedor_id) {
                $this->registrarComisionComoGasto($venta, $unidad, $comision);
            }

            // Solo se mueve el estado si la unidad sigue en el patio. Una que
            // ya está entregada no vuelve a "vendida": el papeleo puede
            // registrarse después de que el cliente se llevó el carro.
            if ($unidad->estado->etapa() !== 'cerrada') {
                $this->cambiarEstado->ejecutar(
                    $unidad,
                    EstadoUnidad::Vendida,
                    "Venta {$venta->numero}",
                );
            }

            return $venta->refresh();
        });
    }

    /**
     * La comisión sobre el margen es lo que alinea al vendedor con el dueño:
     * si regala precio, se corta su propia comisión.
     */
    public function calcularComision(Venta $venta, Unidad $unidad): string
    {
        $porcentaje = (string) $venta->comision_porcentaje;

        if (bccomp($porcentaje, '0', 3) === 0) {
            return '0.00';
        }

        $base = $venta->comision_base === 'precio'
            ? (string) $venta->precio_final
            : bcsub((string) $venta->precio_final, (string) $unidad->costo_total, 2);

        if (bccomp($base, '0.00', 2) <= 0) {
            return '0.00';
        }

        return bcdiv(bcmul($base, $porcentaje, 4), '100', 2);
    }

    protected function registrarComisionComoGasto(Venta $venta, Unidad $unidad, string $comision): void
    {
        $categoria = CategoriaCosto::where('codigo', 'comision_vendedor')->first();

        if (! $categoria) {
            return;
        }

        $this->registrarCosto->ejecutar($unidad, [
            'categoria_costo_id' => $categoria->id,
            'monto' => $comision,
            'fecha' => $venta->fecha,
            'descripcion' => "Comisión de {$venta->vendedor?->name} · venta {$venta->numero}",
            'documento' => $venta->numero,
        ]);
    }
}
