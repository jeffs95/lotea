<?php

namespace App\Filament\Resources\Unidades\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * El historial de la unidad. Solo lectura: se escribe desde la acción de
 * cambio de estado y no se edita nunca.
 */
class TransicionesRelationManager extends RelationManager
{
    protected static string $relationship = 'transiciones';

    protected static ?string $title = 'Historial';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ocurrio_en', 'desc')
            ->columns([
                TextColumn::make('ocurrio_en')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('estado_anterior')->label('Venía de')->badge()->placeholder('—'),
                TextColumn::make('estado_nuevo')->label('Pasó a')->badge(),
                TextColumn::make('dias_en_estado_anterior')
                    ->label('Días en la etapa anterior')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('usuario.name')->label('Quién')->placeholder('Sistema'),
                TextColumn::make('nota')->label('Nota')->wrap()->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
