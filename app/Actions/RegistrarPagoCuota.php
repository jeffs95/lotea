<?php

namespace App\Actions;

use App\Models\Caja;
use App\Models\Cuota;
use App\Models\PagoCuota;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Recibe el abono de una cuota y lo mete a la caja.
 *
 * Un pago que no entra a caja es plata que el sistema dice que cobraron y que
 * no aparece en ningún lado.
 */
class RegistrarPagoCuota
{
    public function __construct(private RegistrarMovimientoCaja $registrarMovimiento) {}

    public function ejecutar(Cuota $cuota, array $datos, ?Caja $caja = null): PagoCuota
    {
        if ($cuota->estaPagada()) {
            throw new DomainException("La cuota {$cuota->numero} ya está pagada.");
        }

        $monto = (string) $datos['monto'];

        if (bccomp($monto, '0.00', 2) <= 0) {
            throw new DomainException('El monto del abono tiene que ser mayor que cero.');
        }

        if (bccomp($monto, $cuota->pendiente, 2) > 0) {
            throw new DomainException(
                'El abono no puede ser mayor que lo pendiente de la cuota (Q '.number_format((float) $cuota->pendiente, 2).').'
            );
        }

        $mora = (string) ($datos['mora'] ?? 0);

        return DB::transaction(function () use ($cuota, $datos, $caja, $monto, $mora) {
            $movimiento = $caja
                ? $this->registrarMovimiento->ejecutar($caja, [
                    'tipo' => 'ingreso',
                    'categoria' => 'cuota',
                    'monto' => bcadd($monto, $mora, 2),
                    'fecha' => $datos['fecha'] ?? now()->toDateString(),
                    'descripcion' => "Cuota {$cuota->numero}/{$cuota->plan->plazo_meses} · {$cuota->plan->cliente->nombre}",
                    'referencia' => $datos['referencia'] ?? null,
                    'documento' => $cuota->plan->numero,
                ], $cuota)
                : null;

            $pago = PagoCuota::create([
                'cuota_id' => $cuota->id,
                'movimiento_caja_id' => $movimiento?->id,
                'recibo' => $datos['recibo'] ?? $this->siguienteRecibo(),
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'monto' => $monto,
                'mora' => $mora,
                'metodo' => $datos['metodo'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'user_id' => Auth::id(),
            ]);

            $this->actualizarCuota($cuota, $monto, $mora, $datos['fecha'] ?? now()->toDateString());

            return $pago;
        });
    }

    protected function actualizarCuota(Cuota $cuota, string $monto, string $mora, string $fecha): void
    {
        $pagado = bcadd((string) $cuota->pagado, $monto, 2);
        $completa = bccomp($pagado, (string) $cuota->total, 2) >= 0;

        $cuota->update([
            'pagado' => $pagado,
            'mora_cobrada' => bcadd((string) $cuota->mora_cobrada, $mora, 2),
            'estado' => $completa ? 'pagada' : 'parcial',
            'pagada_en' => $completa ? $fecha : null,
        ]);

        $this->cerrarPlanSiTerminó($cuota);
    }

    /** Cuando ya no queda ninguna cuota pendiente, el crédito se cancela. */
    protected function cerrarPlanSiTerminó(Cuota $cuota): void
    {
        $plan = $cuota->plan;

        if ($plan->cuotas()->where('estado', '!=', 'pagada')->doesntExist()) {
            $plan->update(['estado' => 'cancelado']);
        }
    }

    protected function siguienteRecibo(): string
    {
        $ultimo = PagoCuota::where('empresa_id', Tenancy::empresaId())->count();

        return 'REC-'.str_pad((string) ($ultimo + 1), 5, '0', STR_PAD_LEFT);
    }
}
