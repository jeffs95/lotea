<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Models\Unidad;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Quién pregunta')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')->required()->maxLength(120),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('email')->label('Correo')->email()->maxLength(120),
                    Select::make('origen')
                        ->options(Lead::ORIGENES)
                        ->default('mostrador')
                        ->required()
                        ->native(false),
                    Textarea::make('mensaje')->rows(3)->columnSpanFull(),
                ]),

            Section::make('Seguimiento')
                ->columns(2)
                ->schema([
                    Select::make('unidad_id')
                        ->label('Unidad de interés')
                        ->options(fn () => Unidad::enInventario()
                            ->with(['marca', 'linea'])
                            ->orderBy('stock_no')
                            ->get()
                            ->mapWithKeys(fn (Unidad $u) => [$u->id => "{$u->stock_no} · {$u->descripcion}"])
                            ->all())
                        ->searchable()
                        ->native(false),

                    Select::make('vendedor_id')
                        ->label('Vendedor asignado')
                        ->relationship('vendedor', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('estado')
                        ->options(Lead::ESTADOS)
                        ->default('nuevo')
                        ->required()
                        ->live()
                        ->native(false),

                    TextInput::make('motivo_perdida')
                        ->label('Motivo')
                        ->maxLength(120)
                        ->visible(fn (callable $get) => $get('estado') === 'perdido'),

                    DateTimePicker::make('primera_respuesta_en')
                        ->label('Primera respuesta')
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->helperText('Se marca sola con el botón «Marcar atendido».'),
                ]),
        ]);
    }
}
