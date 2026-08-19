<?php

namespace App\Filament\Resources\Proveedores\Tables;

use App\Filament\Resources\Proveedores\ProveedorResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProveedoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ProveedorResource::TIPOS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('contacto')->searchable()->toggleable(),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                TextColumn::make('moneda_default')->label('Moneda')->badge()->toggleable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(ProveedorResource::TIPOS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
