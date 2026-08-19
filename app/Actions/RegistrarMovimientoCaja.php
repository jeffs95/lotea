<?php

namespace App\Actions;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\TipoCambio;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mete o saca dinero de una caja.
 *
 * La moneda la manda la caja: no se puede meter un billete de dólar en la
 * caja de quetzales. Si el movimiento es en dólares, se guarda además su
 * equivalente para poder sumar todas las cajas juntas.
 */
class RegistrarMovimientoCaja
{
    public function ejecutar(Caja $caja, array $datos, ?Model $origen = null): MovimientoCaja
    {
        $monto = (string) $datos['monto'];

        if (bccomp($monto, '0.00', 2) <= 0) {
            throw new DomainException('El monto tiene que ser mayor que cero.');
        }

        $tipoCambio = $caja->esEnDolares()
            ? (string) ($datos['tipo_cambio'] ?? $this->tipoCambioDelDia($datos['fecha'] ?? now()))
            : '1';

        return DB::transaction(function () use ($caja, $datos, $origen, $monto, $tipoCambio) {
            return MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => $datos['tipo'],
                'categoria' => $datos['categoria'] ?? 'otro',
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'descripcion' => $datos['descripcion'],
                'moneda' => $caja->moneda,
                'monto' => $monto,
                'tipo_cambio' => $tipoCambio,
                'monto_base' => bcmul($monto, $tipoCambio, 2),
                'referencia' => $datos['referencia'] ?? null,
                'documento' => $datos['documento'] ?? null,
                'origen_type' => $origen ? $origen::class : null,
                'origen_id' => $origen?->getKey(),
                'user_id' => $datos['user_id'] ?? Auth::id(),
            ]);
        });
    }

    protected function tipoCambioDelDia(mixed $fecha): string
    {
        $tasa = TipoCambio::where('moneda', 'USD')
            ->where('fecha', '<=', $fecha instanceof CarbonInterface ? $fecha->toDateString() : $fecha)
            ->orderByDesc('fecha')
            ->value('tasa');

        return (string) ($tasa ?? 1);
    }
}
