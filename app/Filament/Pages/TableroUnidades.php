<?php

namespace App\Filament\Pages;

use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * El patio de un vistazo: en qué etapa está cada carro y cuánta plata hay
 * detenida en cada una.
 *
 * Un listado ordena; un tablero muestra dónde se está atorando la operación.
 */
class TableroUnidades extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tablero';

    protected static ?string $slug = 'tablero';

    protected static ?string $title = 'Tablero del patio';

    protected string $view = 'filament.pages.tablero-unidades';

    public bool $puedeVerCostos = false;

    public function mount(): void
    {
        $this->puedeVerCostos = auth()->user()?->can('ver_costos_unidad') ?? false;
    }

    /** Una columna por estado de inventario, en el orden del ciclo. */
    public function getColumnas(): Collection
    {
        $unidades = Unidad::enInventario()
            ->with(['marca', 'linea'])
            ->orderBy('estado_desde')
            ->get()
            ->groupBy(fn (Unidad $u) => $u->estado->value);

        return collect(EstadoUnidad::cases())
            ->filter(fn (EstadoUnidad $e) => $e->esInventario())
            ->map(function (EstadoUnidad $estado) use ($unidades) {
                $delEstado = $unidades->get($estado->value, collect());

                return [
                    'estado' => $estado,
                    'unidades' => $delEstado,
                    'total' => $delEstado->count(),
                    'capital' => (float) $delEstado->sum('costo_total'),
                ];
            })
            ->values();
    }

    public function getCapitalTotal(): float
    {
        return (float) Unidad::enInventario()->sum('costo_total');
    }
}
