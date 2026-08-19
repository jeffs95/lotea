<?php

namespace App\Filament\Resources\GastosCompartidos\Pages;

use App\Filament\Resources\GastosCompartidos\GastoCompartidoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGastosCompartidos extends ListRecords
{
    protected static string $resource = GastoCompartidoResource::class;

    protected static ?string $title = 'Gastos compartidos';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Registrar gasto compartido')];
    }
}
