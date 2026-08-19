<?php

namespace App\Filament\Resources\Marcas\Pages;

use App\Filament\Resources\Marcas\MarcaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarcas extends ListRecords
{
    protected static string $resource = MarcaResource::class;

    protected static ?string $title = 'Marcas y líneas';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva marca')];
    }
}
