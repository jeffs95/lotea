<?php

namespace App\Filament\Resources\Clientes\Schemas;

use App\Models\Cliente;
use App\Support\LimiteDeSubida;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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

                    SpatieMediaLibraryFileUpload::make('identificacion')
                        ->label('Foto del DPI')
                        ->collection('identificacion')
                        ->multiple()
                        ->maxFiles(4)
                        ->image()
                        ->imageEditor()
                        // Se encoge en el navegador: la foto de un DPI con la
                        // cámara de un teléfono pesa más que el límite del
                        // servidor, y al pasarse PHP descarta la petición
                        // entera sin dejar un error que mostrar.
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('1600')
                        ->maxSize(LimiteDeSubida::KILOBYTES)
                        ->panelLayout('grid')
                        ->columnSpanFull()
                        ->helperText(new HtmlString(
                            'Las dos caras, o el pasaporte si es extranjero. '
                            .'<strong>Queda guardado en privado</strong>: solo lo ve quien entra al panel.<br>'
                            .'Sirve para cuando el traspaso ya está a nombre del comprador y aparece '
                            .'un problema con ese vehículo años después.'
                        )),
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
