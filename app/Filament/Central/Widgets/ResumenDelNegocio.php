<?php

namespace App\Filament\Central\Widgets;

use App\Models\Cobro;
use App\Models\Empresa;
use App\Models\Unidad;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Cómo va el negocio de vender Lotea. */
class ResumenDelNegocio extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $operando = Empresa::where('activa', true)->whereNull('suspendida_en')->with('plan')->get();
        $mrr = $operando->sum(fn (Empresa $e) => $e->mensualidad);

        $suspendidas = Empresa::whereNotNull('suspendida_en')->count();

        $vencido = Cobro::porCobrar()->whereDate('vence_en', '<', now());
        $montoVencido = (float) (clone $vencido)->sum('monto');

        // Sin contexto de empresa el scope no filtra, pero lo pedimos
        // explícito: leer datos de todos los clientes a la vez es a propósito.
        $unidades = Tenancy::sinFiltro(fn () => Unidad::count());

        return [
            Stat::make('Ingreso mensual', 'Q '.number_format($mrr, 2))
                ->description($operando->count().' '.($operando->count() === 1 ? 'cliente operando' : 'clientes operando'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Por cobrar vencido', 'Q '.number_format($montoVencido, 2))
                ->description((clone $vencido)->count().' cobros pasados de fecha')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($montoVencido > 0 ? 'danger' : 'success'),

            Stat::make('Suspendidos', (string) $suspendidas)
                ->description($suspendidas === 0 ? 'Nadie cortado' : 'Sin acceso por falta de pago')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color($suspendidas > 0 ? 'warning' : 'gray'),

            Stat::make('Unidades en el sistema', number_format($unidades))
                ->description('Sumando todos los clientes')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),
        ];
    }
}
