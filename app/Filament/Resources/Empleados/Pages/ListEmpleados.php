<?php

namespace App\Filament\Resources\Empleados\Pages;

use App\Filament\Resources\Empleados\EmpleadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmpleados extends ListRecords
{
    protected static string $resource = EmpleadoResource::class;

    protected static ?string $title = 'Empleados';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo empleado')];
    }
}
