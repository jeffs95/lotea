<?php

namespace App\Filament\Resources\Clientes\Tables;

use App\Models\Cliente;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Cliente $record) => $record->nit ? 'NIT '.$record->nit : null),

                TextColumn::make('telefono')->label('Teléfono')->searchable()->copyable(),
                TextColumn::make('email')->label('Correo')->searchable()->toggleable(),

                TextColumn::make('ventas_count')
                    ->label('Compras')
                    ->counts('ventas')
                    ->badge()
                    ->alignCenter(),

                TextColumn::make('tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => Cliente::TIPOS[$state] ?? $state)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(Cliente::TIPOS),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
