<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo primero que el dueño quiere saber al abrir el sistema: cuánta plata
 * tiene parada y desde cuándo.
 */
class CapitalEnPatio extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $inventario = Unidad::enInventario();

        $unidades = (clone $inventario)->count();
        $capital = (float) (clone $inventario)->sum('costo_total');
        $enRiesgo = (clone $inventario)->get()->filter(fn (Unidad $u) => ($u->dias_inventario ?? 0) > 120);

        $diasPromedio = (clone $inventario)->get()
            ->map->dias_inventario
            ->filter()
            ->avg();

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

            Stat::make('Capital dormido', 'Q '.number_format((float) $enRiesgo->sum('costo_total'), 2))
                ->description($enRiesgo->count().($enRiesgo->count() === 1 ? ' unidad' : ' unidades').' con más de 120 días')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($enRiesgo->isEmpty() ? 'success' : 'danger'),

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
