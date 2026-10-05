<?php

namespace App\Support;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Quién vende y quién dejó de vender.
 *
 * Buena parte de los vendedores no son empleados del patio: tienen sus propias
 * actividades y publican los carros por su cuenta, cobrando por unidad
 * vendida. El dueño necesita saber a quién le rinde seguir trabajando así y a
 * quién no, y esa pregunta no se contesta con un listado de ventas.
 *
 * Por eso el ranking incluye a los que vendieron cero. Un cuadro que solo
 * muestra a los que vendieron contesta «quién vende más» y esconde justo la
 * otra mitad: el que lleva cuatro meses sin cerrar uno no aparece en ninguna
 * fila, y es el que había que mirar.
 */
class RankingDeVendedores
{
    public const PERIODOS = [
        'mes' => 'Este mes',
        'mes_pasado' => 'El mes pasado',
        'trimestre' => 'Últimos tres meses',
        'anio' => 'Este año',
        'todo' => 'Desde siempre',
    ];

    public const PERIODO_POR_DEFECTO = 'mes';

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public static function rango(string $periodo): array
    {
        $hoy = today();

        return match ($periodo) {
            'mes_pasado' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()],
            'trimestre' => [$hoy->copy()->subMonthsNoOverflow(3)->startOfDay(), $hoy],
            'anio' => [$hoy->copy()->startOfYear(), $hoy],
            'todo' => [null, null],
            default => [$hoy->copy()->startOfMonth(), $hoy],
        };
    }

    /**
     * Una fila por vendedor, de mejor a peor.
     *
     * @return Collection<int, array{vendedor: User, carros: int, vendido: string, comisiones: string, ultima: ?Carbon, dias_sin_vender: ?int}>
     */
    public static function para(string $periodo = self::PERIODO_POR_DEFECTO): Collection
    {
        [$desde, $hasta] = self::rango($periodo);

        $delPeriodo = self::resumen($desde, $hasta);

        // La última venta se busca en toda la historia y no en el período: lo
        // que se quiere saber de quien vendió cero este mes es desde cuándo.
        $historico = self::resumen(null, null);

        return self::vendedores($historico->keys())
            ->map(function (User $vendedor) use ($delPeriodo, $historico) {
                $suyo = $delPeriodo->get($vendedor->getKey());
                $ultima = $historico->get($vendedor->getKey())?->ultima;
                $ultima = $ultima ? Carbon::parse($ultima) : null;

                return [
                    'vendedor' => $vendedor,
                    'carros' => (int) ($suyo->carros ?? 0),
                    'vendido' => bcadd((string) ($suyo->vendido ?? '0'), '0.00', 2),
                    'comisiones' => bcadd((string) ($suyo->comisiones ?? '0'), '0.00', 2),
                    'ultima' => $ultima,
                    'dias_sin_vender' => $ultima ? (int) $ultima->diffInDays(today()) : null,
                ];
            })
            ->sortByDesc(fn (array $fila) => [$fila['carros'], $fila['vendido']])
            ->values();
    }

    /** Los totales de cada vendedor en un rango, en una sola consulta. */
    protected static function resumen(?Carbon $desde, ?Carbon $hasta): Collection
    {
        return Venta::cerradas()
            ->whereNotNull('vendedor_id')
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->groupBy('vendedor_id')
            ->selectRaw('vendedor_id')
            ->selectRaw('count(*) as carros')
            ->selectRaw('coalesce(sum(precio_final), 0) as vendido')
            ->selectRaw('coalesce(sum(comision_monto), 0) as comisiones')
            ->selectRaw('max(fecha) as ultima')
            ->get()
            ->keyBy('vendedor_id');
    }

    /**
     * A quién se le pone una fila.
     *
     * A los que tienen el rol, aunque nunca hayan vendido —el que acaba de
     * entrar tiene que aparecer—, y a quien tenga ventas aunque ya le hayan
     * quitado el rol, para que su historia no desaparezca del cuadro.
     *
     * @param  Collection<int, int>  $conVentas
     * @return Collection<int, User>
     */
    protected static function vendedores(Collection $conVentas): Collection
    {
        return User::query()
            ->whereHas('empresas', fn ($q) => $q->whereKey(Tenancy::empresaId()))
            ->where(fn ($q) => $q
                ->whereKey($conVentas->all())
                ->orWhereHas('roles', fn ($r) => $r->where('name', 'vendedor')))
            ->orderBy('name')
            ->get();
    }
}
