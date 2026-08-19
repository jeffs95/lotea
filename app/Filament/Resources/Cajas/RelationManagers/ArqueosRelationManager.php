<?php

namespace App\Filament\Resources\Cajas\RelationManagers;

use App\Models\Arqueo;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArqueosRelationManager extends RelationManager
{
    protected static string $relationship = 'arqueos';

    protected static ?string $title = 'Arqueos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('realizado_en')->label('Fecha')->dateTime('d/m/Y H:i'),
                TextColumn::make('saldo_sistema')->label('Sistema')->money('GTQ', locale: 'es_GT')->alignEnd(),
                TextColumn::make('saldo_contado')->label('Contado')->money('GTQ', locale: 'es_GT')->alignEnd(),

                TextColumn::make('diferencia')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Arqueo $record) => $record->cuadro() ? 'success' : 'danger')
                    ->description(fn (Arqueo $record) => $record->justificacion),

                TextColumn::make('usuario.name')->label('Quién')->placeholder('—'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
