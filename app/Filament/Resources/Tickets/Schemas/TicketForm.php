<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('¿Qué pasó?')
                ->description('Contanos en dos líneas. El sistema adjunta solo el resto de los datos que necesitamos.')
                ->schema([
                    TextInput::make('asunto')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('No puedo agregar un vehículo'),

                    Textarea::make('mensaje')
                        ->label('Contanos con más detalle')
                        ->required()
                        ->rows(5)
                        ->placeholder('Entro a Unidades y no me aparece el botón de nueva unidad.'),

                    TextInput::make('pantalla')
                        ->label('¿En qué pantalla estabas?')
                        ->maxLength(160)
                        ->placeholder('Unidades')
                        ->helperText('Opcional, pero nos ayuda a llegar más rápido.'),
                ]),
        ]);
    }
}
