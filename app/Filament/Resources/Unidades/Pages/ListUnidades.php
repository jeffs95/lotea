<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Unidad;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListUnidades extends ListRecords
{
    protected static string $resource = UnidadResource::class;

    protected static ?string $title = 'Unidades';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva unidad')];
    }

    /** Las pestañas siguen las etapas del negocio, no los 16 estados sueltos. */
    public function getTabs(): array
    {
        return [
            'inventario' => Tab::make('En inventario')
                ->modifyQueryUsing(fn ($query) => $query->enInventario())
                ->badge(fn () => Unidad::enInventario()->count()),

            'importacion' => Tab::make('En camino')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', self::estadosDe('importacion')))
                ->badge(fn () => Unidad::whereIn('estado', self::estadosDe('importacion'))->count()),

            'preparacion' => Tab::make('En preparación')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', self::estadosDe('preparacion')))
                ->badge(fn () => Unidad::whereIn('estado', self::estadosDe('preparacion'))->count()),

            'venta' => Tab::make('A la venta')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', self::estadosDe('venta')))
                ->badge(fn () => Unidad::whereIn('estado', self::estadosDe('venta'))->count()),

            'cerradas' => Tab::make('Vendidas')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('estado', self::estadosDe('cerrada'))),

            // Lo que quedó a medias en el levantamiento: el trabajo pendiente.
            'incompletas' => Tab::make('Por completar')
                ->modifyQueryUsing(fn ($query) => $query->enInventario()->incompletas())
                ->badge(fn () => Unidad::enInventario()->incompletas()->count())
                ->badgeColor('warning'),

            'todas' => Tab::make('Todas'),
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
