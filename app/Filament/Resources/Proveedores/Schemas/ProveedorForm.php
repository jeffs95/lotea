<?php

namespace App\Filament\Resources\Proveedores\Schemas;

use App\Filament\Resources\Proveedores\ProveedorResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Select::make('tipo')
                        ->options(ProveedorResource::TIPOS)
                        ->required()
                        ->native(false),
                    TextInput::make('nombre')->required()->maxLength(160),
                    TextInput::make('nit')->label('NIT')->maxLength(20),
                    Select::make('pais')
                        ->label('País')
                        ->options(['GT' => 'Guatemala', 'US' => 'Estados Unidos', 'MX' => 'México', 'SV' => 'El Salvador', 'HN' => 'Honduras'])
                        ->default('GT')
                        ->required()
                        ->native(false),
                    Select::make('moneda_default')
                        ->label('Moneda habitual')
                        ->options(['GTQ' => 'Quetzales (GTQ)', 'USD' => 'Dólares (USD)'])
                        ->default('GTQ')
                        ->required()
                        ->native(false)
                        ->helperText('Con la que suele facturar. Se puede cambiar en cada gasto.'),
                ]),

            Section::make('Contacto')
                ->columns(2)
                ->schema([
                    TextInput::make('contacto')->maxLength(120),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('email')->email()->maxLength(120),
                    Toggle::make('activo')->default(true),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
