<?php

namespace App\Filament\Central\Resources\Tickets\Tables;

use App\Filament\Central\Pages\DiagnosticoDePermisos;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['usuario', 'empresa']))
            ->defaultSort('created_at', 'asc')   // lo más viejo primero: es lo que más arde
            ->emptyStateHeading('Bandeja vacía')
            ->emptyStateDescription('Ningún cliente reportó problemas.')
            ->columns([
                TextColumn::make('numero')->label('No.')->badge()->color('gray'),

                TextColumn::make('created_at')
                    ->label('Entró')
                    ->since()
                    ->description(fn (Ticket $record) => $record->created_at->format('d/m/Y H:i'))
                    ->sortable(),

                TextColumn::make('empresa.nombre_comercial')
                    ->label('Concesionario')
                    ->searchable(['nombre', 'nombre_comercial'])
                    ->weight('medium')
                    ->state(fn (Ticket $record) => $record->empresa->nombre_comercial ?: $record->empresa->nombre),

                TextColumn::make('asunto')
                    ->wrap()
                    ->searchable()
                    ->description(fn (Ticket $record) => trim($record->usuario?->name.' · '.($record->contexto['rol'] ?? 'sin rol'), ' ·')),

                TextColumn::make('contexto.pantalla')
                    ->label('Pantalla')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('horas_esperando')
                    ->label('Esperando')
                    ->badge()
                    ->placeholder('Respondido')
                    ->formatStateUsing(fn (?int $state) => $state === null ? 'Respondido' : ($state < 1 ? 'menos de 1 h' : "{$state} h"))
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'success',
                        $state <= 4 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Ticket::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'resuelto' => 'success',
                        'en_proceso' => 'info',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')->options(Ticket::ESTADOS)->multiple(),
                SelectFilter::make('empresa')->relationship('empresa', 'nombre')->searchable()->preload(),
                Filter::make('pendientes')
                    ->label('Solo sin resolver')
                    ->query(fn ($query) => $query->pendientes()),
            ])
            ->recordActions([
                Action::make('leer')
                    ->label('Leer y responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->modalHeading(fn (Ticket $record) => "{$record->numero} · {$record->asunto}")
                    ->modalDescription(fn (Ticket $record) => $record->mensaje)
                    ->fillForm(fn (Ticket $record) => [
                        'estado' => $record->estado === 'abierto' ? 'en_proceso' : $record->estado,
                        'respuesta' => $record->respuesta,
                    ])
                    ->schema([
                        Textarea::make('respuesta')
                            ->label('Tu respuesta')
                            ->required()
                            ->rows(4)
                            ->helperText('El cliente la ve dentro de su propio panel.'),

                        Select::make('estado')
                            ->options(Ticket::ESTADOS)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Ticket $record, array $data) {
                        $record->update([
                            ...$data,
                            'respondido_por' => auth()->id(),
                            'respondido_en' => now(),
                        ]);

                        Notification::make()->title('Respuesta enviada')->success()->send();
                    }),

                // El camino de dos clics: del ticket a los permisos de quien lo
                // reportó, que es la causa más común del problema.
                Action::make('verPermisos')
                    ->label('Ver sus permisos')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (Ticket $record) => $record->user_id !== null)
                    ->url(fn (Ticket $record) => DiagnosticoDePermisos::getUrl().'?empresaId='.$record->empresa_id.'&usuarioId='.$record->user_id),
            ]);
    }
}
