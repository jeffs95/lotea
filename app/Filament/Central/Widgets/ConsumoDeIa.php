<?php

namespace App\Filament\Central\Widgets;

use App\Models\Empresa;
use App\Models\LecturaIa;
use App\Support\TarifaDeIa;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que cuesta el add-on de IA y lo que se cobra por él.
 *
 * Sirve para una sola pregunta: ¿el precio que le puse deja margen?
 */
class ConsumoDeIa extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    /** Solo aparece cuando hay clientes con el módulo contratado. */
    public static function canView(): bool
    {
        return Empresa::whereHas('plan', fn ($q) => $q->whereJsonContains('modulos', 'ia'))->exists();
    }

    protected function getStats(): array
    {
        $delMes = Tenancy::sinFiltro(fn () => LecturaIa::delMes());

        $lecturas = (clone $delMes)->exitosas()->count();
        $fallidas = (clone $delMes)->where('exitosa', false)->count();
        $costoUsd = (float) (clone $delMes)->sum('costo_usd');
        $costoQ = TarifaDeIa::enQuetzales($costoUsd);

        $conElModulo = Empresa::whereHas('plan', fn ($q) => $q->whereJsonContains('modulos', 'ia'))
            ->whereNull('suspendida_en')
            ->where('activa', true)
            ->count();

        return [
            Stat::make('Lecturas este mes', number_format($lecturas))
                ->description($fallidas > 0 ? "{$fallidas} fallidas" : 'Ninguna falló')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),

            Stat::make('Costo del mes', 'Q '.number_format($costoQ, 2))
                ->description('$ '.number_format($costoUsd, 4).' en OpenRouter')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($costoQ > 200 ? 'warning' : 'success'),

            Stat::make('Costo por lectura', $lecturas > 0
                    ? 'Q '.number_format($costoQ / $lecturas, 4)
                    : '—')
                ->description($conElModulo.' '.($conElModulo === 1 ? 'cliente con el módulo' : 'clientes con el módulo'))
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }
}
