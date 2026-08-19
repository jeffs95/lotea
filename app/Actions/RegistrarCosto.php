<?php

namespace App\Actions;

use App\Models\CostoUnidad;
use App\Models\TipoCambio;
use App\Models\Unidad;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mete un gasto a una unidad, en la moneda en que se pagó.
 *
 * Si el gasto viene en dólares, aquí se resuelve el tipo de cambio y se guarda
 * el monto en quetzales junto con la tasa usada. Convertir "al final" es como
 * se pierde margen sin darse cuenta.
 */
class RegistrarCosto
{
    public function __construct(private RecalcularCostoUnidad $recalcular) {}

    public function ejecutar(Unidad $unidad, array $datos): CostoUnidad
    {
        return DB::transaction(function () use ($unidad, $datos) {
            $moneda = $datos['moneda'] ?? 'GTQ';
            $tipoCambio = $this->resolverTipoCambio($moneda, $datos['tipo_cambio'] ?? null, $datos['fecha'] ?? now());

            $costo = CostoUnidad::create([
                'unidad_id' => $unidad->id,
                'categoria_costo_id' => $datos['categoria_costo_id'],
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'moneda' => $moneda,
                'monto' => $datos['monto'],
                'tipo_cambio' => $tipoCambio,
                'monto_base' => self::aMonedaBase($datos['monto'], $tipoCambio),
                'es_presupuesto' => $datos['es_presupuesto'] ?? false,
                'documento' => $datos['documento'] ?? null,
                'prorrateado_de_id' => $datos['prorrateado_de_id'] ?? null,
                'user_id' => $datos['user_id'] ?? Auth::id(),
            ]);

            $this->recalcular->ejecutar($unidad);

            return $costo;
        });
    }

    /** Multiplicación con bcmath: en dinero, los float mienten. */
    public static function aMonedaBase(string|float|int $monto, string|float|int $tipoCambio): string
    {
        return bcmul((string) $monto, (string) $tipoCambio, 2);
    }

    /**
     * El tipo de cambio del documento manda; si no viene, se usa el de
     * referencia del día. El que compra dólares en la calle no usa el del
     * banco, por eso se puede sobreescribir siempre.
     */
    protected function resolverTipoCambio(string $moneda, string|float|null $explicito, CarbonInterface|string $fecha): string
    {
        if ($moneda === 'GTQ') {
            return '1';
        }

        if (filled($explicito)) {
            return (string) $explicito;
        }

        $referencia = TipoCambio::where('moneda', $moneda)
            ->where('fecha', '<=', $fecha instanceof CarbonInterface ? $fecha->toDateString() : $fecha)
            ->orderByDesc('fecha')
            ->value('tasa');

        return (string) ($referencia ?? 1);
    }
}
