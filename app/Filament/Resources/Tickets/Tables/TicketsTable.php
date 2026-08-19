<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Models\Ticket;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['usuario']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Sin reportes')
            ->emptyStateDescription('Si algo no te funciona, contanos y lo revisamos.')
            ->columns([
                TextColumn::make('numero')->label('No.')->badge()->color('gray'),
                TextColumn::make('created_at')->label('Enviado')->since()->sortable(),

                TextColumn::make('asunto')
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Ticket $record) => $record->usuario?->name),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Ticket::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'resuelto' => 'success',
                        'en_proceso' => 'info',
                        default => 'warning',
                    }),

                TextColumn::make('respondido_en')
                    ->label('Respondido')
                    ->since()
                    ->placeholder('Esperando respuesta'),
            ])
            ->recordActions([ViewAction::make()->label('Ver')]);
    }
}
