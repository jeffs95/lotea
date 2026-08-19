<?php

namespace App\Actions;

use App\Models\GastoCompartido;
use App\Models\Unidad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Registra un gasto de varias unidades y lo reparte de una vez.
 */
class RegistrarGastoCompartido
{
    public function __construct(private ProrratearGasto $prorratear) {}

    /** @param  Collection<int, Unidad>|array<int, int>  $unidades */
    public function ejecutar(array $datos, Collection|array $unidades): GastoCompartido
    {
        return DB::transaction(function () use ($datos, $unidades) {
            $moneda = $datos['moneda'] ?? 'GTQ';
            $tipoCambio = $moneda === 'GTQ' ? '1' : (string) ($datos['tipo_cambio'] ?? 1);

            $gasto = GastoCompartido::create([
                'categoria_costo_id' => $datos['categoria_costo_id'],
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'descripcion' => $datos['descripcion'],
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'moneda' => $moneda,
                'monto' => $datos['monto'],
                'tipo_cambio' => $tipoCambio,
                'monto_base' => RegistrarCosto::aMonedaBase($datos['monto'], $tipoCambio),
                'criterio' => $datos['criterio'] ?? 'partes_iguales',
                'documento' => $datos['documento'] ?? null,
                'es_presupuesto' => $datos['es_presupuesto'] ?? false,
                'user_id' => $datos['user_id'] ?? Auth::id(),
            ]);

            $modelos = collect($unidades)->map(
                fn ($u) => $u instanceof Unidad ? $u : Unidad::findOrFail($u)
            );

            $this->prorratear->ejecutar($gasto, $modelos);

            return $gasto->refresh();
        });
    }
}
