<?php

namespace App\Filament\Resources\GastosCompartidos\Tables;

use App\Models\GastoCompartido;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GastosCompartidosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->description(fn (GastoCompartido $record) => collect([
                        $record->proveedor?->nombre,
                        $record->documento,
                    ])->filter()->implode(' · ') ?: null)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('categoria.nombre')->label('Categoría')->badge()->color('gray'),

                TextColumn::make('monto_base')
                    ->label('Total')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('porciones_count')
                    ->label('Unidades')
                    ->counts('porciones')
                    ->badge()
                    ->alignCenter(),

                TextColumn::make('criterio')
                    ->label('Reparto')
                    ->formatStateUsing(fn (string $state) => GastoCompartido::CRITERIOS[$state] ?? $state)
                    ->toggleable(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
