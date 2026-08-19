<?php

namespace App\Filament\Resources\Creditos\RelationManagers;

use App\Actions\RegistrarPagoCuota;
use App\Models\Caja;
use App\Models\Cuota;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class CuotasRelationManager extends RelationManager
{
    protected static string $relationship = 'cuotas';

    protected static ?string $title = 'Plan de cuotas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->columns([
                TextColumn::make('numero')->label('#')->alignCenter(),

                TextColumn::make('vence_en')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->description(fn (Cuota $record) => $record->estaVencida()
                        ? $record->dias_de_atraso.' días de atraso'
                        : null)
                    ->color(fn (Cuota $record) => $record->estaVencida() ? 'danger' : null),

                TextColumn::make('capital')->money('GTQ', locale: 'es_GT')->alignEnd()->toggleable(),
                TextColumn::make('interes')->label('Interés')->money('GTQ', locale: 'es_GT')->alignEnd()->toggleable(),

                TextColumn::make('total')
                    ->label('Cuota')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium')
                    ->summarize(Sum::make()->label('Total')->money('GTQ', locale: 'es_GT')),

                TextColumn::make('pagado')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->color('success')
                    ->summarize(Sum::make()->label('Cobrado')->money('GTQ', locale: 'es_GT')),

                TextColumn::make('mora')
                    ->label('Mora')
                    ->state(fn (Cuota $record) => (float) $record->moraAlDia())
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->color('danger')
                    ->placeholder('—'),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (Cuota $record) => match (true) {
                        $record->estaPagada() => 'Pagada',
                        $record->estaVencida() => 'Vencida',
                        $record->estado === 'parcial' => 'Abono parcial',
                        default => 'Pendiente',
                    })
                    ->color(fn (Cuota $record) => match (true) {
                        $record->estaPagada() => 'success',
                        $record->estaVencida() => 'danger',
                        $record->estado === 'parcial' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Filter::make('pendientes')->label('Solo pendientes')->query(fn ($query) => $query->pendientes()),
                Filter::make('vencidas')->label('Solo vencidas')->query(fn ($query) => $query->vencidas()),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('cobrar')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Cuota $record) => ! $record->estaPagada())
                    ->fillForm(fn (Cuota $record) => [
                        'monto' => $record->pendiente,
                        'mora' => $record->moraAlDia(),
                    ])
                    ->schema([
                        Select::make('caja_id')
                            ->label('¿A qué caja entra?')
                            ->options(fn () => Caja::activas()->where('moneda', 'GTQ')->pluck('nombre', 'id'))
                            ->native(false)
                            ->helperText('Si lo dejás vacío, el pago se registra sin tocar caja.'),

                        TextInput::make('monto')->label('Abono a la cuota')->numeric()->required()->prefix('Q'),
                        TextInput::make('mora')->label('Mora cobrada')->numeric()->default(0)->prefix('Q'),
                        DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                        TextInput::make('metodo')->label('Método')->maxLength(40)->placeholder('Efectivo, depósito'),
                        TextInput::make('referencia')->maxLength(60)->placeholder('No. de boleta'),
                    ])
                    ->action(function (Cuota $record, array $data) {
                        try {
                            $pago = app(RegistrarPagoCuota::class)->ejecutar(
                                $record,
                                $data,
                                filled($data['caja_id'] ?? null) ? Caja::find($data['caja_id']) : null,
                            );

                            Notification::make()
                                ->title('Pago registrado')
                                ->body("Recibo {$pago->recibo}.")
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
