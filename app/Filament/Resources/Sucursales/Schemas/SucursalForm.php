<?php

namespace App\Filament\Resources\Sucursales\Schemas;

use App\Support\Coordenadas;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
                    TextInput::make('whatsapp')
                        ->label('WhatsApp')
                        ->tel()
                        ->maxLength(30)
                        ->helperText('Si lo deja vacío se usa el teléfono.'),
                    TextInput::make('encargado')->maxLength(120),
                    TextInput::make('horario')
                        ->label('Horario de atención')
                        ->maxLength(120)
                        ->placeholder('Lun a Vie 8:00–18:00, Sáb 8:00–13:00'),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Cómo llegar')
                ->description('Con esto el portal pone los botones de Google Maps y Waze para que el comprador llegue solo.')
                ->columns(2)
                ->schema([
                    TextInput::make('enlace_mapa')
                        ->label('Pegue aquí el enlace de Google Maps')
                        ->placeholder('https://maps.app.goo.gl/...')
                        ->columnSpanFull()
                        ->dehydrated(false)
                        ->live(onBlur: true)
                        // Nadie se sabe sus coordenadas, pero cualquiera abre
                        // Maps, busca su patio y le da a «Compartir».
                        ->afterStateUpdated(function (?string $state, callable $set) {
                            if (blank($state)) {
                                return;
                            }

                            $punto = Coordenadas::desde($state);

                            if (! $punto) {
                                Notification::make()
                                    ->title('No se pudieron sacar las coordenadas de ese enlace')
                                    ->body('Abra el lugar en Google Maps, toque «Compartir» y pegue el enlace completo. También puede escribir la latitud y la longitud a mano.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $set('latitud', $punto['latitud']);
                            $set('longitud', $punto['longitud']);

                            Notification::make()
                                ->title('Ubicación tomada del enlace')
                                ->success()
                                ->send();
                        })
                        ->helperText('Busque el patio en Google Maps, toque «Compartir» y pegue el enlace. Las coordenadas se llenan solas.'),

                    TextInput::make('latitud')
                        ->numeric()
                        ->step(0.0000001)
                        ->minValue(-90)
                        ->maxValue(90)
                        ->placeholder('14.6349'),

                    TextInput::make('longitud')
                        ->numeric()
                        ->step(0.0000001)
                        ->minValue(-180)
                        ->maxValue(180)
                        ->placeholder('-90.5069'),
                ]),

            Section::make('Estado')
                ->columns(3)
                ->schema([
                    Toggle::make('es_principal')
                        ->label('Es la casa matriz')
                        ->helperText('La que se usa por defecto al recibir unidades.'),
                    Toggle::make('activa')->default(true),
                    Toggle::make('mostrar_en_portal')
                        ->label('Mostrar en el portal')
                        ->default(true)
                        ->helperText('Aparece en «Dónde encontrarnos». Apáguelo para una bodega que no recibe clientes.'),
                ]),
        ]);
    }
}
