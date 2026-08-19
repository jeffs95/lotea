<?php

namespace App\Filament\Resources\Cajas\RelationManagers;

use App\Actions\AnularMovimientoCaja;
use App\Models\MovimientoCaja;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** El estado de cuenta de la caja. Solo lectura: se registra con las acciones. */
class MovimientosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimientos';

    protected static ?string $title = 'Movimientos';

    public function table(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contraparte']))
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),

                TextColumn::make('descripcion')
                    ->label('Concepto')
                    ->wrap()
                    ->searchable()
                    ->description(fn (MovimientoCaja $record) => collect([
                        MovimientoCaja::CATEGORIAS[$record->categoria] ?? null,
                        $record->referencia,
                    ])->filter()->implode(' · ')),

                TextColumn::make('monto')
                    ->label('Monto')
                    ->alignEnd()
                    ->weight('medium')
                    ->formatStateUsing(fn (MovimientoCaja $record) => ($record->esIngreso() ? '+ ' : '− ')
                        .($record->moneda === 'USD' ? '$ ' : 'Q ')
                        .number_format((float) $record->monto, 2))
                    ->color(fn (MovimientoCaja $record) => match (true) {
                        $record->estaAnulado() => 'gray',
                        $record->esIngreso() => 'success',
                        default => 'danger',
                    }),

                TextColumn::make('estado')
                    ->badge()
                    ->state(fn (MovimientoCaja $record) => $record->estaAnulado() ? 'Anulado' : 'Vigente')
                    ->color(fn (MovimientoCaja $record) => $record->estaAnulado() ? 'danger' : 'success')
                    ->description(fn (MovimientoCaja $record) => $record->motivo_anulacion),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(MovimientoCaja::TIPOS),
                SelectFilter::make('categoria')->options(MovimientoCaja::CATEGORIAS),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MovimientoCaja $record) => ! $record->estaAnulado())
                    ->schema([
                        Textarea::make('motivo')->label('¿Por qué se anula?')->required()->rows(2),
                    ])
                    ->action(function (MovimientoCaja $record, array $data) {
                        try {
                            app(AnularMovimientoCaja::class)->ejecutar($record, $data['motivo']);

                            Notification::make()->title('Movimiento anulado')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
