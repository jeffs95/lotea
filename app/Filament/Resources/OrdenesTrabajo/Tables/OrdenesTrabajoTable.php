<?php

namespace App\Filament\Resources\OrdenesTrabajo\Tables;

use App\Actions\AnularOrdenTrabajo;
use App\Actions\CerrarOrdenTrabajo;
use App\Models\OrdenTrabajo;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdenesTrabajoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['jefe', 'unidad.marca', 'unidad.linea']))
            ->defaultSort('abierta_en', 'desc')
            ->columns([
                TextColumn::make('numero')->label('No.')->weight('bold')->searchable(),

                TextColumn::make('unidad.stock_no')
                    ->label('Unidad')
                    ->description(fn (OrdenTrabajo $record) => $record->unidad->descripcion)
                    ->searchable(),

                TextColumn::make('tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => OrdenTrabajo::TIPOS[$state] ?? $state),

                TextColumn::make('jefe.nombre_completo')->label('Responsable')->placeholder('—')->toggleable(),

                TextColumn::make('dias_en_taller')
                    ->label('Días')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 15 => 'danger',
                        $state > 7 => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('total')
                    ->label('Costo del trabajo')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium')
                    ->description(fn (OrdenTrabajo $record) => 'MO Q'.number_format((float) $record->total_mano_obra, 0)
                        .' · Rep Q'.number_format((float) $record->total_repuestos, 0)
                        .' · 3ros Q'.number_format((float) $record->total_terceros, 0))
                    ->visible(fn () => auth()->user()?->can('ver_costos_unidad') ?? false),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrdenTrabajo::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'cerrada' => 'success',
                        'terminada' => 'info',
                        'anulada' => 'danger',
                        'en_proceso' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (OrdenTrabajo $record) => $record->motivo_anulacion),
            ])
            ->filters([
                SelectFilter::make('estado')->options(OrdenTrabajo::ESTADOS)->multiple(),
                SelectFilter::make('tipo')->options(OrdenTrabajo::TIPOS),
                Filter::make('abiertas')->label('Solo abiertas')->query(fn ($query) => $query->abiertas()),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('cerrar')
                    ->label('Cerrar y cargar costo')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (OrdenTrabajo $record) => 'Se le cargarán Q '
                        .number_format((float) $record->total, 2)." al costo de la unidad {$record->unidad->stock_no}.")
                    ->visible(fn (OrdenTrabajo $record) => ! $record->estaCerrada())
                    ->action(function (OrdenTrabajo $record) {
                        try {
                            app(CerrarOrdenTrabajo::class)->ejecutar($record);

                            Notification::make()
                                ->title('Orden cerrada')
                                ->body('El costo del trabajo ya está en la ficha de la unidad.')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OrdenTrabajo $record) => ! $record->estaAnulada())
                    ->schema([
                        Textarea::make('motivo')
                            ->label('¿Por qué se anula?')
                            ->required()
                            ->rows(2)
                            ->helperText('Si ya había cargado costo a la unidad, se le quita.'),
                    ])
                    ->action(function (OrdenTrabajo $record, array $data) {
                        try {
                            app(AnularOrdenTrabajo::class)->ejecutar($record, $data['motivo']);

                            Notification::make()->title('Orden anulada')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
