<?php

namespace App\Filament\Resources\Ventas\Pages;

use App\Filament\Resources\Ventas\VentaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected static ?string $title = 'Ventas';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva venta')];
    }
}
