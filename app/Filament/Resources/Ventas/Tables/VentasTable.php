<?php

namespace App\Filament\Resources\Ventas\Tables;

use App\Actions\AnularVenta;
use App\Actions\CobrarVentaEnCaja;
use App\Actions\GenerarPlanPago;
use App\Filament\Resources\Creditos\PlanPagoResource;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VentasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('numero')->label('No.')->searchable()->weight('bold'),
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),

                TextColumn::make('unidad.stock_no')
                    ->label('Unidad')
                    ->description(fn (Venta $record) => $record->unidad->descripcion)
                    ->searchable(),

                TextColumn::make('cliente.nombre')->label('Cliente')->searchable(),

                TextColumn::make('precio_final')
                    ->label('Precio')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->description(fn (Venta $record) => $record->descuento > 0
                        ? 'Desc. Q '.number_format((float) $record->descuento, 2)
                        : null),

                // Solo para quien puede ver costos: es la utilidad real.
                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->color(fn (Venta $record) => (float) $record->utilidad >= 0 ? 'success' : 'danger')
                    ->description(fn (Venta $record) => $record->margen !== null
                        ? number_format($record->margen, 1).'% de margen'
                        : null)
                    ->visible(fn () => auth()->user()?->can('ver_costos_unidad') ?? false),

                TextColumn::make('comision_monto')
                    ->label('Comisión')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->description(fn (Venta $record) => $record->vendedor?->name)
                    ->toggleable(),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Venta::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'cerrada' => 'success',
                        'reservada' => 'warning',
                        'anulada' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')->options(Venta::ESTADOS)->multiple(),
                SelectFilter::make('forma_pago')->label('Forma de pago')->options(Venta::FORMAS_PAGO),
                SelectFilter::make('vendedor')->relationship('vendedor', 'name'),
            ])
            ->recordActions([
                Action::make('cobrar')
                    ->label('Registrar cobro')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Venta $record) => ! $record->estaAnulada() && Caja::activas()->exists())
                    ->schema([
                        Select::make('caja_id')
                            ->label('¿A qué caja entra?')
                            ->options(fn () => Caja::activas()->pluck('nombre', 'id'))
                            ->required()
                            ->native(false),

                        Select::make('categoria')
                            ->label('Concepto')
                            ->options([
                                'venta' => MovimientoCaja::CATEGORIAS['venta'],
                                'enganche' => MovimientoCaja::CATEGORIAS['enganche'],
                            ])
                            ->default(fn (Venta $record) => $record->forma_pago === 'contado' ? 'venta' : 'enganche')
                            ->required()
                            ->native(false),

                        TextInput::make('monto')
                            ->numeric()
                            ->required()
                            ->prefix('Q')
                            ->default(fn (Venta $record) => $record->forma_pago === 'contado'
                                ? $record->precio_final
                                : $record->enganche),

                        DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                        TextInput::make('referencia')->maxLength(60)->placeholder('Boleta, cheque'),
                    ])
                    ->action(function (Venta $record, array $data) {
                        try {
                            app(CobrarVentaEnCaja::class)->ejecutar($record, Caja::findOrFail($data['caja_id']), $data);

                            Notification::make()->title('Cobro registrado en caja')->success()->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('financiar')
                    ->label('Generar plan de pagos')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->visible(fn (Venta $record) => $record->esACreditoPropio()
                        && $record->estaCerrada()
                        && ! $record->estaAnulada()
                        && ! $record->planPago()->exists())
                    ->schema([
                        TextInput::make('enganche')
                            ->numeric()
                            ->required()
                            ->prefix('Q')
                            ->default(fn (Venta $record) => $record->enganche ?? 0)
                            ->helperText('Lo que ya dejó el cliente. El resto es lo que se financia.'),

                        TextInput::make('plazo_meses')->label('Plazo en meses')->numeric()->required()->default(24)->minValue(1),
                        TextInput::make('tasa_anual')->label('Tasa anual')->numeric()->default(18)->suffix('%'),
                        TextInput::make('tasa_mora_anual')->label('Tasa de mora anual')->numeric()->default(36)->suffix('%'),
                        DatePicker::make('primera_cuota')->label('Primera cuota')->required()->default(now()->addMonth())->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->action(function (Venta $record, array $data) {
                        try {
                            $plan = app(GenerarPlanPago::class)->ejecutar($record, $data);

                            Notification::make()
                                ->title('Plan de pagos generado')
                                ->body("{$plan->numero}: {$plan->plazo_meses} cuotas de Q "
                                    .number_format((float) $plan->cuota_mensual, 2).'.')
                                ->success()
                                ->actions([
                                    \Filament\Actions\Action::make('verPlan')
                                        ->label('Ver el plan')
                                        ->url(PlanPagoResource::getUrl('edit', ['record' => $plan])),
                                ])
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make()->visible(fn (Venta $record) => ! $record->estaAnulada()),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Venta $record) => ! $record->estaAnulada())
                    ->schema([
                        Textarea::make('motivo')
                            ->label('¿Por qué se anula?')
                            ->required()
                            ->rows(2)
                            ->helperText('La unidad vuelve al patio y la comisión se anula.'),
                    ])
                    ->action(function (Venta $record, array $data) {
                        try {
                            app(AnularVenta::class)->ejecutar($record, $data['motivo']);

                            Notification::make()
                                ->title('Venta anulada')
                                ->body('La unidad volvió al inventario.')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
