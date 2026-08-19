<?php

namespace App\Filament\Central\Resources\Cobros\Tables;

use App\Models\Cobro;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CobrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('vence_en', 'desc')
            ->columns([
                TextColumn::make('periodo')->label('Periodo')->badge()->color('gray')->sortable(),

                TextColumn::make('empresa.nombre')
                    ->label('Concesionario')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Cobro $record) => $record->concepto),

                TextColumn::make('monto')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('GTQ', locale: 'es_GT')),

                TextColumn::make('vence_en')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Cobro $record) => $record->dias_de_mora > 0
                        ? $record->dias_de_mora.' días de mora'
                        : null)
                    ->color(fn (Cobro $record) => $record->estaVencido() ? 'danger' : null),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (Cobro $record) => $record->estaVencido()
                        ? 'Vencido'
                        : (Cobro::ESTADOS[$record->estado] ?? $record->estado))
                    ->color(fn (Cobro $record) => match (true) {
                        $record->estaPagado() => 'success',
                        $record->estaVencido() => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('pagado_en')
                    ->label('Pagado')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->description(fn (Cobro $record) => $record->referencia)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('estado')->options(Cobro::ESTADOS)->multiple(),
                SelectFilter::make('empresa')->relationship('empresa', 'nombre')->searchable()->preload(),

                Filter::make('vencidos')
                    ->label('Solo vencidos')
                    ->query(fn ($query) => $query->porCobrar()->whereDate('vence_en', '<', now())),
            ])
            ->recordActions([
                Action::make('marcarPagado')
                    ->label('Marcar pagado')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Cobro $record) => ! $record->estaPagado())
                    ->schema([
                        DatePicker::make('pagado_en')->label('Fecha del pago')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                        TextInput::make('metodo_pago')->label('Método')->maxLength(40)->placeholder('Transferencia'),
                        TextInput::make('referencia')->label('Referencia')->maxLength(60)->placeholder('No. de boleta'),
                    ])
                    ->action(function (Cobro $record, array $data) {
                        $record->update([...$data, 'estado' => 'pagado']);

                        Notification::make()->title('Cobro registrado')->success()->send();
                    }),

                EditAction::make(),
            ]);
    }
}
