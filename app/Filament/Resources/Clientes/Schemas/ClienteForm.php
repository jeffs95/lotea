<?php

namespace App\Filament\Resources\Clientes\Schemas;

use App\Models\Cliente;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClienteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Select::make('tipo')->options(Cliente::TIPOS)->default('persona')->required()->native(false),
                    TextInput::make('nombre')->required()->maxLength(160),
                    TextInput::make('nit')->label('NIT')->maxLength(20),
                    TextInput::make('dpi')->label('DPI')->maxLength(20),
                ]),

            Section::make('Contacto')
                ->columns(2)
                ->schema([
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('telefono_alterno')->label('Teléfono alterno')->tel()->maxLength(30),
                    TextInput::make('email')->label('Correo')->email()->maxLength(120),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
