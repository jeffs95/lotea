<?php

namespace App\Filament\Resources\OrdenesTrabajo\Pages;

use App\Filament\Resources\OrdenesTrabajo\OrdenTrabajoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrdenesTrabajo extends ListRecords
{
    protected static string $resource = OrdenTrabajoResource::class;

    protected static ?string $title = 'Órdenes de trabajo';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Abrir orden')];
    }
}
