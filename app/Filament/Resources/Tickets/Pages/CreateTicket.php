<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Actions\AbrirTicket;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = 'Reportar un problema';

    protected function handleRecordCreation(array $data): Model
    {
        $ticket = app(AbrirTicket::class)->ejecutar(auth()->user(), $data, request());

        Notification::make()
            ->title('Recibido')
            ->body("Tu reporte {$ticket->numero} ya está con nosotros. Te respondemos por aquí mismo.")
            ->success()
            ->send();

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
