<?php

namespace App\Filament\Resources\Marcas\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class LineasRelationManager extends RelationManager
{
    protected static string $relationship = 'lineas';

    protected static ?string $title = 'Líneas';

    protected static ?string $modelLabel = 'línea';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nombre')
                ->required()
                ->maxLength(80)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('slug')->required()->maxLength(80),
            Toggle::make('activo')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->searchable()->sortable(),
                TextColumn::make('origen')
                    ->label('Origen')
                    ->badge()
                    ->state(fn ($record) => $record->esDelSistema() ? 'Del sistema' : 'Propia')
                    ->color(fn ($record) => $record->esDelSistema() ? 'gray' : 'success'),
                IconColumn::make('activo')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Nueva línea'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn ($record) => ! $record->esDelSistema()),
                DeleteAction::make()->visible(fn ($record) => ! $record->esDelSistema()),
            ]);
    }
}
