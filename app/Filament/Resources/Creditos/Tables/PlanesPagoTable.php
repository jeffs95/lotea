<?php

namespace App\Filament\Resources\Creditos\Tables;

use App\Models\PlanPago;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlanesPagoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cliente', 'venta.unidad.marca', 'venta.unidad.linea', 'cuotas']))
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('numero')->label('No.')->weight('bold')->searchable(),

                TextColumn::make('cliente.nombre')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (PlanPago $record) => $record->venta->unidad->stock_no
                        .' · '.$record->venta->unidad->descripcion),

                TextColumn::make('cuota_mensual')
                    ->label('Cuota')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->description(fn (PlanPago $record) => $record->plazo_meses.' meses al '
                        .rtrim(rtrim(number_format((float) $record->tasa_anual, 2), '0'), '.').'%'),

                TextColumn::make('avance')
                    ->label('Avance')
                    ->alignCenter()
                    ->state(fn (PlanPago $record) => $record->cuotas_pagadas.' / '.$record->plazo_meses)
                    ->badge()
                    ->color(fn (PlanPago $record) => $record->cuotas_pagadas === $record->plazo_meses ? 'success' : 'gray'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('mora')
                    ->label('Atraso')
                    ->badge()
                    ->state(fn (PlanPago $record) => $record->estaEnMora()
                        ? $record->dias_de_mora.' días'
                        : 'Al día')
                    ->color(fn (PlanPago $record) => match (true) {
                        ! $record->estaEnMora() => 'success',
                        $record->dias_de_mora > 60 => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (PlanPago $record) => $record->estaEnMora()
                        ? $record->cuotasVencidas()->count().' cuotas vencidas'
                        : null),

                IconColumn::make('gps_instalado')->label('GPS')->boolean()->toggleable(),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PlanPago::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'cancelado' => 'success',
                        'recuperado', 'anulado' => 'danger',
                        default => 'info',
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')->options(PlanPago::ESTADOS)->multiple(),

                Filter::make('en_mora')
                    ->label('Solo los atrasados')
                    ->query(fn ($query) => $query->whereHas('cuotas', fn ($q) => $q->vencidas())),

                Filter::make('con_gps')->label('Con GPS')->query(fn ($query) => $query->where('gps_instalado', true)),
            ])
            ->recordActions([
                EditAction::make()->label('Ver plan'),
            ]);
    }
}
