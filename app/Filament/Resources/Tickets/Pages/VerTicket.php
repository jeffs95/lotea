<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VerTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string
    {
        return "{$this->record->numero} · {$this->record->asunto}";
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tu reporte')
                ->schema([
                    TextEntry::make('mensaje')->hiddenLabel()->prose(),
                    TextEntry::make('created_at')->label('Enviado')->dateTime('d/m/Y H:i'),
                    TextEntry::make('estado')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => Ticket::ESTADOS[$state] ?? $state)
                        ->color(fn (string $state) => match ($state) {
                            'resuelto' => 'success',
                            'en_proceso' => 'info',
                            default => 'warning',
                        }),
                ]),

            Section::make('Nuestra respuesta')
                ->visible(fn (Ticket $record) => filled($record->respuesta))
                ->schema([
                    TextEntry::make('respuesta')->hiddenLabel()->prose(),
                    TextEntry::make('respondido_en')->label('Respondido')->dateTime('d/m/Y H:i'),
                ]),
        ]);
    }
}
