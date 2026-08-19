<?php

namespace App\Filament\Resources\Cajas\Schemas;

use App\Models\Caja;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CajaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')->required()->maxLength(80)->placeholder('Caja chica Roosevelt'),

                    Select::make('sucursal_id')
                        ->label('Sucursal')
                        ->relationship('sucursal', 'nombre')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('tipo')->options(Caja::TIPOS)->default('efectivo')->required()->live()->native(false),

                    Select::make('moneda')
                        ->options(['GTQ' => 'Quetzales (GTQ)', 'USD' => 'Dólares (USD)'])
                        ->default('GTQ')
                        ->required()
                        ->native(false)
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated()
                        ->helperText('No se cambia después: los movimientos ya registrados quedarían en otra moneda.'),

                    TextInput::make('saldo_inicial')
                        ->label('Saldo inicial')
                        ->numeric()
                        ->default(0)
                        ->prefix(fn (callable $get) => $get('moneda') === 'USD' ? '$' : 'Q')
                        ->helperText('Lo que había cuando empezaron a usar el sistema.'),
                ]),

            Section::make('Datos del banco')
                ->columns(2)
                ->visible(fn (callable $get) => $get('tipo') === 'banco')
                ->schema([
                    TextInput::make('banco')->maxLength(60),
                    TextInput::make('numero_cuenta')->label('No. de cuenta')->maxLength(40),
                ]),

            Section::make()
                ->columns(2)
                ->schema([
                    Toggle::make('activa')->default(true),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
