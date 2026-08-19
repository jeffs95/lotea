<?php

namespace App\Filament\Resources\Cajas\Actions;

use App\Actions\ArquearCaja;
use App\Actions\RegistrarMovimientoCaja;
use App\Actions\TrasladarEntreCajas;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/** Las tres cosas que se hacen con una caja: mover, trasladar y contar. */
class AccionesDeCaja
{
    public static function registrarMovimiento(): Action
    {
        return Action::make('registrarMovimiento')
            ->label('Registrar movimiento')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->visible(fn (Caja $record) => $record->activa)
            ->schema([
                Select::make('tipo')->options(MovimientoCaja::TIPOS)->default('ingreso')->required()->native(false),
                Select::make('categoria')->options(MovimientoCaja::CATEGORIAS)->default('otro')->required()->native(false),
                TextInput::make('monto')
                    ->numeric()
                    ->required()
                    ->prefix(fn (Caja $record) => $record->esEnDolares() ? '$' : 'Q'),
                DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                TextInput::make('descripcion')->label('Descripción')->required()->maxLength(160),
                TextInput::make('referencia')->maxLength(60)->placeholder('Boleta, recibo, cheque'),
            ])
            ->action(function (Caja $record, array $data) {
                try {
                    app(RegistrarMovimientoCaja::class)->ejecutar($record, $data);

                    Notification::make()
                        ->title('Movimiento registrado')
                        ->body('Saldo actual: Q '.number_format((float) $record->fresh()->saldo, 2))
                        ->success()
                        ->send();
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function trasladar(): Action
    {
        return Action::make('trasladar')
            ->label('Trasladar a otra caja')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Caja $record) => $record->activa)
            ->schema([
                Select::make('destino_id')
                    ->label('Caja destino')
                    ->options(fn (Caja $record) => Caja::activas()
                        ->whereKeyNot($record->id)
                        ->where('moneda', $record->moneda)
                        ->pluck('nombre', 'id'))
                    ->required()
                    ->native(false)
                    ->helperText('Solo cajas de la misma moneda.'),

                TextInput::make('monto')->numeric()->required()->prefix(fn (Caja $record) => $record->esEnDolares() ? '$' : 'Q'),
                DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                TextInput::make('referencia')->maxLength(60),
            ])
            ->action(function (Caja $record, array $data) {
                try {
                    app(TrasladarEntreCajas::class)->ejecutar($record, Caja::findOrFail($data['destino_id']), $data);

                    Notification::make()->title('Traslado registrado')->success()->send();
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function arquear(): Action
    {
        return Action::make('arquear')
            ->label('Arquear')
            ->icon('heroicon-o-calculator')
            ->color('warning')
            ->schema([
                TextInput::make('saldo_sistema')
                    ->label('Según el sistema')
                    ->prefix(fn (Caja $record) => $record->esEnDolares() ? '$' : 'Q')
                    ->default(fn (Caja $record) => number_format((float) $record->saldo, 2, '.', ''))
                    ->disabled(),

                TextInput::make('saldo_contado')
                    ->label('Lo que contaste')
                    ->numeric()
                    ->required()
                    ->prefix(fn (Caja $record) => $record->esEnDolares() ? '$' : 'Q'),

                Textarea::make('justificacion')
                    ->label('Si hay diferencia, ¿de qué es?')
                    ->rows(2),
            ])
            ->action(function (Caja $record, array $data) {
                $arqueo = app(ArquearCaja::class)->ejecutar($record, $data['saldo_contado'], $data['justificacion'] ?? null);

                Notification::make()
                    ->title($arqueo->cuadro() ? 'La caja cuadra' : 'Hay diferencia')
                    ->body($arqueo->cuadro()
                        ? 'Lo contado coincide con el sistema.'
                        : 'Diferencia de Q '.number_format((float) $arqueo->diferencia, 2).'. Queda registrada.')
                    ->color($arqueo->cuadro() ? 'success' : 'warning')
                    ->send();
            });
    }
}
