<?php

namespace App\Filament\Resources\Usuarios\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable()->copyable(),
                TextColumn::make('roles.name')->label('Roles')->badge()->separator(','),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                TextColumn::make('ultimo_acceso_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->toggleable(),
                IconColumn::make('activo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
