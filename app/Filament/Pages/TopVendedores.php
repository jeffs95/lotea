<?php

namespace App\Filament\Pages;

use App\Support\RankingDeVendedores;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Quién vende y quién dejó de vender.
 *
 * El dueño trabaja con vendedores que no son empleados suyos: publican los
 * carros por su cuenta y cobran por unidad vendida. La pregunta que se hace
 * cada tanto es a quién le rinde seguir así y a quién ya no, y en el listado
 * de ventas esa respuesta no está: hay que sumarla a mano, y al que lleva
 * cuatro meses sin cerrar uno no se le ve porque no tiene filas.
 *
 * El cuadro se mira con el permiso de ver las ventas ajenas, que es
 * exactamente lo que enseña. No se le hizo permiso propio porque uno aparte
 * daría a elegir algo que no se puede separar: el ranking ya dice cuánto
 * cobró cada quien.
 */
class TopVendedores extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Vendedores';

    protected static ?string $slug = 'vendedores';

    protected static ?string $title = 'Quién vende';

    protected string $view = 'filament.pages.top-vendedores';

    public string $periodo = RankingDeVendedores::PERIODO_POR_DEFECTO;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_ventas_ajenas') ?? false;
    }

    public function getPeriodos(): array
    {
        return RankingDeVendedores::PERIODOS;
    }

    public function getFilas(): Collection
    {
        return RankingDeVendedores::para($this->periodo);
    }

    /** Los totales del período, para no obligar a sumar la columna a ojo. */
    public function getTotales(): array
    {
        $filas = $this->getFilas();

        return [
            'carros' => $filas->sum('carros'),
            'vendido' => $filas->reduce(fn ($suma, $f) => bcadd($suma, $f['vendido'], 2), '0.00'),
            'comisiones' => $filas->reduce(fn ($suma, $f) => bcadd($suma, $f['comisiones'], 2), '0.00'),
            'activos' => $filas->where('carros', '>', 0)->count(),
        ];
    }
}
