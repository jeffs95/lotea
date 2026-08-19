<?php

namespace App\Filament\Resources\Marcas\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('lineas_count')->label('Líneas')->counts('lineas')->badge(),
                TextColumn::make('origen')
                    ->label('Origen')
                    ->badge()
                    ->state(fn ($record) => $record->esDelSistema() ? 'Del sistema' : 'Propia')
                    ->color(fn ($record) => $record->esDelSistema() ? 'gray' : 'success'),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('empresa_id')
                    ->label('Origen')
                    ->placeholder('Todas')
                    ->trueLabel('Solo las mías')
                    ->falseLabel('Solo las del sistema')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('empresa_id'),
                        false: fn ($query) => $query->whereNull('empresa_id'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => ! $record->esDelSistema()),
                DeleteAction::make()->visible(fn ($record) => ! $record->esDelSistema()),
            ]);
    }
}
