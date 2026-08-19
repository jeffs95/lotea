<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected static ?string $title = 'Prospectos';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo prospecto')];
    }
}
