<?php

namespace App\Filament\Central\Resources\Planes\Tables;

use App\Models\Plan;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->weight('bold')->searchable(),

                TextColumn::make('precio_mensual')
                    ->label('Precio')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('limites')
                    ->label('Límites')
                    ->state(fn (Plan $record) => collect([
                        $record->limiteTexto($record->max_sucursales, 'sucursal', 'sucursales'),
                        $record->limiteTexto($record->max_usuarios, 'usuario', 'usuarios'),
                        $record->limiteTexto($record->max_unidades_activas, 'unidad', 'unidades'),
                    ])->implode(' · '))
                    ->wrap()
                    ->color('gray'),

                TextColumn::make('modulos')
                    ->label('Módulos')
                    ->state(fn (Plan $record) => count($record->modulos ?? []))
                    ->badge()
                    ->alignCenter(),

                TextColumn::make('empresas_count')
                    ->label('Clientes')
                    ->counts('empresas')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                IconColumn::make('activo')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }
}
