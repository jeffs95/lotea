<?php

namespace App\Actions;

use App\Models\CostoUnidad;
use App\Models\GastoCompartido;
use App\Models\Unidad;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reparte un gasto compartido entre las unidades que lo causaron.
 *
 * El flete de un contenedor con 4 carros no es "más o menos Q2,000 cada uno":
 * si el total es Q8,000.01, alguien tiene que quedarse con el centavo. Aquí se
 * decide de forma explícita para que la suma de las porciones sea exacta.
 */
class ProrratearGasto
{
    public function __construct(private RecalcularCostoUnidad $recalcular) {}

    /**
     * @param  Collection<int, Unidad>|array<int, Unidad>  $unidades
     * @return Collection<int, CostoUnidad>
     */
    public function ejecutar(GastoCompartido $gasto, Collection|array $unidades): Collection
    {
        $unidades = collect($unidades)->values();

        if ($unidades->isEmpty()) {
            throw new DomainException('Hay que decir entre qué unidades se reparte el gasto.');
        }

        return DB::transaction(function () use ($gasto, $unidades) {
            // Si se está re-prorrateando, las porciones anteriores se van: son
            // un cálculo derivado, no un hecho económico propio.
            $gasto->porciones()->delete();

            $porciones = $this->calcularPorciones($gasto, $unidades);

            $creados = $unidades->map(function (Unidad $unidad, int $i) use ($gasto, $porciones) {
                $montoBase = $porciones[$i];

                return CostoUnidad::create([
                    'unidad_id' => $unidad->id,
                    'categoria_costo_id' => $gasto->categoria_costo_id,
                    'proveedor_id' => $gasto->proveedor_id,
                    'descripcion' => $gasto->descripcion.' (prorrateo)',
                    'fecha' => $gasto->fecha,
                    'moneda' => 'GTQ',
                    'monto' => $montoBase,
                    'tipo_cambio' => 1,
                    'monto_base' => $montoBase,
                    'es_presupuesto' => $gasto->es_presupuesto,
                    'prorrateado_de_id' => $gasto->id,
                    'documento' => $gasto->documento,
                    'user_id' => $gasto->user_id,
                ]);
            });

            $unidades->each(fn (Unidad $u) => $this->recalcular->ejecutar($u));

            return $creados;
        });
    }

    /**
     * Devuelve las porciones en quetzales, ya cuadradas contra el total.
     *
     * @return array<int, string>
     */
    protected function calcularPorciones(GastoCompartido $gasto, Collection $unidades): array
    {
        $total = (string) $gasto->monto_base;
        $n = $unidades->count();

        $porciones = match ($gasto->criterio) {
            'por_valor' => $this->porValor($total, $unidades),
            default => $this->partesIguales($total, $n),
        };

        // El redondeo siempre deja un residuo. Se lo lleva la primera unidad
        // para que la suma de las porciones dé exactamente el total.
        $sumado = array_reduce($porciones, fn ($acc, $p) => bcadd($acc, $p, 2), '0.00');
        $residuo = bcsub($total, $sumado, 2);

        if (bccomp($residuo, '0.00', 2) !== 0) {
            $porciones[0] = bcadd($porciones[0], $residuo, 2);
        }

        return $porciones;
    }

    /** @return array<int, string> */
    protected function partesIguales(string $total, int $n): array
    {
        $porcion = bcdiv($total, (string) $n, 2);

        return array_fill(0, $n, $porcion);
    }

    /**
     * Proporcional a lo que ya costó cada unidad: el carro caro carga más
     * flete que el barato. Si ninguna tiene costo todavía, se reparte parejo.
     *
     * @return array<int, string>
     */
    protected function porValor(string $total, Collection $unidades): array
    {
        $suma = $unidades->reduce(fn ($acc, Unidad $u) => bcadd($acc, (string) $u->costo_total, 2), '0.00');

        if (bccomp($suma, '0.00', 2) === 0) {
            return $this->partesIguales($total, $unidades->count());
        }

        return $unidades
            ->map(fn (Unidad $u) => bcdiv(bcmul($total, (string) $u->costo_total, 6), $suma, 2))
            ->all();
    }
}
