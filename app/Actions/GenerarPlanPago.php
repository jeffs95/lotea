<?php

namespace App\Actions;

use App\Models\Cuota;
use App\Models\PlanPago;
use App\Models\Venta;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Arma la tabla de amortización de una venta a crédito propio.
 *
 * Cuota nivelada (sistema francés): el cliente paga siempre lo mismo, y lo que
 * cambia mes a mes es cuánto de eso es interés y cuánto abona a la deuda. El
 * residuo del redondeo cae en la última cuota para que la suma cierre exacta.
 */
class GenerarPlanPago
{
    public function ejecutar(Venta $venta, array $datos): PlanPago
    {
        if ($venta->estaAnulada()) {
            throw new DomainException('No se puede financiar una venta anulada.');
        }

        if ($venta->planPago()->exists()) {
            throw new DomainException("La venta {$venta->numero} ya tiene un plan de pagos.");
        }

        $enganche = (string) ($datos['enganche'] ?? $venta->enganche ?? 0);
        $financiado = bcsub((string) $venta->precio_final, $enganche, 2);

        if (bccomp($financiado, '0.00', 2) <= 0) {
            throw new DomainException('No queda saldo por financiar: el enganche cubre todo el precio.');
        }

        $plazo = (int) $datos['plazo_meses'];

        if ($plazo < 1) {
            throw new DomainException('El plazo tiene que ser de al menos un mes.');
        }

        $tasaAnual = (string) ($datos['tasa_anual'] ?? 0);
        $cuota = $this->cuotaNivelada($financiado, $tasaAnual, $plazo);

        return DB::transaction(function () use ($venta, $datos, $enganche, $financiado, $plazo, $tasaAnual, $cuota) {
            $plan = PlanPago::create([
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'numero' => $datos['numero'] ?? $this->siguienteNumero(),
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'precio_venta' => $venta->precio_final,
                'enganche' => $enganche,
                'monto_financiado' => $financiado,
                'tasa_anual' => $tasaAnual,
                'tasa_mora_anual' => $datos['tasa_mora_anual'] ?? 0,
                'plazo_meses' => $plazo,
                'cuota_mensual' => $cuota,
                'primera_cuota' => $datos['primera_cuota'] ?? now()->addMonth()->toDateString(),
                'gps_instalado' => $datos['gps_instalado'] ?? false,
                'gps_referencia' => $datos['gps_referencia'] ?? null,
                'notas' => $datos['notas'] ?? null,
                'user_id' => Auth::id(),
            ]);

            $this->generarCuotas($plan);

            return $plan->refresh();
        });
    }

    /** cuota = P · i / (1 − (1+i)^−n), o P/n si no hay interés. */
    public function cuotaNivelada(string $capital, string $tasaAnual, int $plazo): string
    {
        if (bccomp($tasaAnual, '0', 3) === 0) {
            return bcdiv($capital, (string) $plazo, 2);
        }

        $i = (float) bcdiv(bcdiv($tasaAnual, '100', 10), '12', 10);
        $cuota = ((float) $capital * $i) / (1 - pow(1 + $i, -$plazo));

        return number_format($cuota, 2, '.', '');
    }

    protected function generarCuotas(PlanPago $plan): void
    {
        $saldo = (string) $plan->monto_financiado;
        $mensual = bcdiv(bcdiv((string) $plan->tasa_anual, '100', 10), '12', 10);
        $cuota = (string) $plan->cuota_mensual;

        for ($numero = 1; $numero <= $plan->plazo_meses; $numero++) {
            $interes = bcmul($saldo, $mensual, 2);
            $capital = bcsub($cuota, $interes, 2);
            $total = $cuota;

            // La última cuota se lleva lo que quede: el redondeo de todas las
            // anteriores tiene que cerrar en cero.
            if ($numero === $plan->plazo_meses) {
                $capital = $saldo;
                $total = bcadd($capital, $interes, 2);
            }

            $saldo = bcsub($saldo, $capital, 2);

            Cuota::create([
                'plan_pago_id' => $plan->id,
                'numero' => $numero,
                'vence_en' => $plan->primera_cuota->copy()->addMonths($numero - 1)->toDateString(),
                'capital' => $capital,
                'interes' => $interes,
                'total' => $total,
                'saldo_despues' => $saldo,
                'estado' => 'pendiente',
            ]);
        }
    }

    protected function siguienteNumero(): string
    {
        $ultimo = PlanPago::where('empresa_id', Tenancy::empresaId())->count();

        return 'CR-'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }
}
