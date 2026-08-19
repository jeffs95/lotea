<?php

namespace App\Filament\Central\Resources\Planes\Pages;

use App\Filament\Central\Resources\Planes\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanes extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Planes';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo plan')];
    }
}
