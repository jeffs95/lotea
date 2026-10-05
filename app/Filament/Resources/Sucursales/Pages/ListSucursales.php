<?php

namespace App\Filament\Resources\Sucursales\Pages;

use App\Filament\Resources\Sucursales\SucursalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSucursales extends ListRecords
{
    /**
     * Por qué no está el botón de agregar.
     *
     * Se esconde al llegar al tope del plan, y un botón que ayer
     * estaba y hoy no deja a la gente buscándolo. Mejor decirlo aquí.
     */
    public function getSubheading(): ?string
    {
        return SucursalResource::avisoDelTope();
    }

    protected static string $resource = SucursalResource::class;

    protected static ?string $title = 'Sucursales';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva sucursal')];
    }
}
