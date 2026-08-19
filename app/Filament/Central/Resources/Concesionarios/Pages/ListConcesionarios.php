<?php

namespace App\Filament\Central\Resources\Concesionarios\Pages;

use App\Filament\Central\Resources\Concesionarios\ConcesionarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConcesionarios extends ListRecords
{
    protected static string $resource = ConcesionarioResource::class;

    protected static ?string $title = 'Concesionarios';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Dar de alta')];
    }
}
