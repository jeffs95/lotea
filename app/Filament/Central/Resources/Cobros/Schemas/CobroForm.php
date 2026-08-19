<?php

namespace App\Filament\Central\Resources\Cobros\Schemas;

use App\Models\Cobro;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CobroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('empresa_id')
                        ->label('Concesionario')
                        ->relationship('empresa', 'nombre')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('plan_id')->label('Plan')->relationship('plan', 'nombre')->native(false),

                    TextInput::make('periodo')
                        ->required()
                        ->maxLength(7)
                        ->placeholder('2026-08')
                        ->rule('regex:/^\d{4}-\d{2}$/')
                        ->helperText('Año-mes. Solo puede haber un cobro por periodo y cliente.'),

                    TextInput::make('monto')->numeric()->required()->prefix('Q'),
                    TextInput::make('concepto')->maxLength(160)->columnSpanFull(),
                    DatePicker::make('vence_en')->label('Vence')->required()->native(false)->displayFormat('d/m/Y'),

                    Select::make('estado')->options(Cobro::ESTADOS)->default('pendiente')->required()->live()->native(false),
                ]),

            Section::make('Pago')
                ->columns(3)
                ->visible(fn (callable $get) => $get('estado') === 'pagado')
                ->schema([
                    DatePicker::make('pagado_en')->label('Pagado el')->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('metodo_pago')->label('Método')->maxLength(40)->placeholder('Transferencia, depósito'),
                    TextInput::make('referencia')->maxLength(60)->placeholder('No. de boleta'),
                ]),

            Textarea::make('notas')->rows(2)->columnSpanFull(),
        ]);
    }
}
