<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Lo primero que el dueño quiere saber al abrir el sistema: cuánta plata
 * tiene parada y desde cuándo.
 */
class CapitalEnPatio extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Desde cuántos días en el patio el capital se considera dormido. */
    public const DIAS_DE_RIESGO = 120;

    protected function getStats(): array
    {
        $inventario = Unidad::enInventario();

        $unidades = (clone $inventario)->count();
        $capital = (float) (clone $inventario)->sum('costo_total');

        // Todo en SQL: antes se traía el inventario completo a memoria para
        // filtrarlo y promediarlo, y con doscientas unidades eso se nota.
        $enRiesgo = (clone $inventario)->whereDate('fecha_compra', '<', now()->subDays(self::DIAS_DE_RIESGO));

        $unidadesEnRiesgo = (clone $enRiesgo)->count();
        $capitalEnRiesgo = (float) (clone $enRiesgo)->sum('costo_total');

        $diasPromedio = (clone $inventario)
            ->whereNotNull('fecha_compra')
            ->avg(DB::raw('extract(day from (coalesce(fecha_venta, now()) - fecha_compra))'));

        return [
            Stat::make('Capital en patio', 'Q '.number_format($capital, 2))
                ->description($unidades.($unidades === 1 ? ' unidad' : ' unidades').' en inventario')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Días promedio en patio', $diasPromedio ? round($diasPromedio).' días' : '—')
                ->description('De la compra a hoy')
                ->descriptionIcon('heroicon-m-clock')
                ->color(match (true) {
                    $diasPromedio === null => 'gray',
                    $diasPromedio > 90 => 'danger',
                    $diasPromedio > 60 => 'warning',
                    default => 'success',
                }),

            Stat::make('Capital dormido', 'Q '.number_format($capitalEnRiesgo, 2))
                ->description($unidadesEnRiesgo.($unidadesEnRiesgo === 1 ? ' unidad' : ' unidades').' con más de '.self::DIAS_DE_RIESGO.' días')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($unidadesEnRiesgo === 0 ? 'success' : 'danger'),

            Stat::make('En camino', (string) Unidad::whereIn('estado', self::estadosDe('importacion'))->count())
                ->description('Unidades en tránsito o aduana')
                ->descriptionIcon('heroicon-m-globe-americas')
                ->color('info'),
        ];
    }

    protected static function estadosDe(string $etapa): array
    {
        return collect(EstadoUnidad::cases())
            ->filter(fn (EstadoUnidad $e) => $e->etapa() === $etapa)
            ->map->value
            ->values()
            ->all();
    }
}
