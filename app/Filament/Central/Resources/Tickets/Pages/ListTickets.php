<?php

namespace App\Filament\Central\Resources\Tickets\Pages;

use App\Filament\Central\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = 'Bandeja de soporte';
}
