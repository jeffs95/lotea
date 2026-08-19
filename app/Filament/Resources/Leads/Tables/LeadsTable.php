<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vendedor', 'unidad.marca', 'unidad.linea']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Entró')
                    ->since()
                    ->description(fn (Lead $record) => $record->created_at->format('d/m/Y H:i'))
                    ->sortable(),

                TextColumn::make('nombre')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Lead $record) => collect([$record->telefono, $record->email])->filter()->implode(' · ')),

                TextColumn::make('unidad.stock_no')
                    ->label('Le interesa')
                    ->placeholder('—')
                    ->description(fn (Lead $record) => $record->unidad?->descripcion),

                TextColumn::make('origen')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => Lead::ORIGENES[$state] ?? $state),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Lead::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'nuevo' => 'warning',
                        'ganado' => 'success',
                        'perdido' => 'danger',
                        default => 'info',
                    }),

                // La métrica que el dueño va a mirar todos los días.
                TextColumn::make('minutos_de_respuesta')
                    ->label('Respuesta')
                    ->badge()
                    ->placeholder('Sin atender')
                    ->formatStateUsing(fn (?int $state) => $state === null
                        ? 'Sin atender'
                        : ($state < 60 ? "{$state} min" : round($state / 60).' h'))
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'danger',
                        $state <= 30 => 'success',
                        $state <= 240 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('Sin asignar')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')->options(Lead::ESTADOS)->multiple(),
                SelectFilter::make('origen')->options(Lead::ORIGENES),
                Filter::make('sin_atender')
                    ->label('Solo sin atender')
                    ->query(fn ($query) => $query->whereNull('primera_respuesta_en')),
            ])
            ->recordActions([
                Action::make('atendido')
                    ->label('Marcar atendido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Lead $record) => $record->estaSinAtender())
                    ->action(function (Lead $record) {
                        $record->update([
                            'primera_respuesta_en' => now(),
                            'ultimo_contacto_en' => now(),
                            'estado' => $record->estado === 'nuevo' ? 'contactado' : $record->estado,
                            'vendedor_id' => $record->vendedor_id ?? auth()->id(),
                        ]);

                        Notification::make()->title('Prospecto atendido')->success()->send();
                    }),

                EditAction::make(),
            ]);
    }
}
