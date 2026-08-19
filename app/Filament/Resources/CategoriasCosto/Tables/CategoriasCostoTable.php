<?php

namespace App\Filament\Resources\CategoriasCosto\Tables;

use App\Filament\Resources\CategoriasCosto\CategoriaCostoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoriasCostoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->defaultGroup('grupo')
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('codigo')->label('Código')->badge()->color('gray')->searchable(),
                IconColumn::make('afecta_costo')->label('Suma al costo')->boolean(),
                IconColumn::make('prorrateable')->label('Prorrateable')->boolean(),
                IconColumn::make('es_sistema')->label('Del sistema')->boolean()->toggleable(),
                IconColumn::make('activa')->boolean(),
            ])
            ->filters([
                SelectFilter::make('grupo')->options(CategoriaCostoResource::GRUPOS),
            ])
            ->recordActions([
                EditAction::make(),
                // Las del sistema se pueden desactivar, pero no borrar: hay
                // gastos históricos colgando de ellas.
                DeleteAction::make()->visible(fn ($record) => ! $record->es_sistema),
            ]);
    }
}
