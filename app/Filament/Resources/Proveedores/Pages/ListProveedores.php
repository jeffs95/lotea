<?php

namespace App\Filament\Resources\Proveedores\Pages;

use App\Filament\Resources\Proveedores\ProveedorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProveedores extends ListRecords
{
    protected static string $resource = ProveedorResource::class;

    protected static ?string $title = 'Proveedores';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo proveedor')];
    }
}
