<?php

namespace App\Filament\Resources\Usuarios\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles']))
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
