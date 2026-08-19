<?php

namespace App\Filament\Resources\Sucursales\Pages;

use App\Filament\Resources\Sucursales\SucursalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSucursal extends EditRecord
{
    protected static string $resource = SucursalResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
