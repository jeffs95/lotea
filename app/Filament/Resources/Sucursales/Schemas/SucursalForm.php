<?php

namespace App\Filament\Resources\Sucursales\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SucursalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la sucursal')
                ->columns(2)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(20)
                        ->scopedUnique(ignoreRecord: true)
                        ->helperText('Corto y estable: PRIN, Z11, XELA. Aparece en el número de stock.'),
                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('encargado')->maxLength(120),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Estado')
                ->columns(2)
                ->schema([
                    Toggle::make('es_principal')
                        ->label('Es la casa matriz')
                        ->helperText('La que se usa por defecto al recibir unidades.'),
                    Toggle::make('activa')->default(true),
                ]),
        ]);
    }
}
