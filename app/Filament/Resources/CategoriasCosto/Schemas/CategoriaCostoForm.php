<?php

namespace App\Filament\Resources\CategoriasCosto\Schemas;

use App\Filament\Resources\CategoriasCosto\CategoriaCostoResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoriaCostoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set, $record) => $record
                            ? null
                            : $set('codigo', Str::of($state ?? '')->slug('_')->limit(30, ''))),
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(40)
                        ->scopedUnique(ignoreRecord: true)
                        ->disabled(fn ($record) => $record?->es_sistema)
                        ->dehydrated(fn ($record) => ! $record?->es_sistema)
                        ->helperText('Identificador interno. No se cambia una vez que hay gastos registrados.'),
                    Select::make('grupo')
                        ->options(CategoriaCostoResource::GRUPOS)
                        ->required()
                        ->native(false),
                    TextInput::make('orden')
                        ->numeric()
                        ->default(999)
                        ->helperText('Define el orden en la ficha de costo.'),
                ]),

            Section::make('Comportamiento')
                ->columns(2)
                ->schema([
                    Toggle::make('afecta_costo')
                        ->label('Suma al costo de la unidad')
                        ->default(true)
                        ->helperText('Apagado para gastos que no encarecen el carro, como la comisión del vendedor.'),
                    Toggle::make('prorrateable')
                        ->label('Se puede prorratear')
                        ->helperText('Para gastos de varias unidades a la vez: flete de contenedor, honorarios de una póliza.'),
                    Toggle::make('activa')->default(true),
                ]),
        ]);
    }
}
