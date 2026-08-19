<?php

namespace App\Filament\Resources\Sucursales\Pages;

use App\Filament\Resources\Sucursales\SucursalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSucursales extends ListRecords
{
    protected static string $resource = SucursalResource::class;

    protected static ?string $title = 'Sucursales';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva sucursal')];
    }
}
