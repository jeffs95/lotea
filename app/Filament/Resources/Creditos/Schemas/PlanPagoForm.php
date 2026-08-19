<?php

namespace App\Filament\Resources\Creditos\Schemas;

use App\Models\PlanPago;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * El plan no se edita: las condiciones se fijan al generarlo desde la venta.
 * Aquí solo se ajusta lo que no cambia las cuentas.
 */
class PlanPagoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Condiciones')
                ->description('Se fijaron al generar el plan. Cambiarlas rehaciendo el cálculo desordenaría lo ya cobrado.')
                ->columns(4)
                ->schema([
                    TextInput::make('numero')->label('No.')->disabled(),
                    TextInput::make('monto_financiado')->label('Financiado')->prefix('Q')->disabled(),
                    TextInput::make('cuota_mensual')->label('Cuota')->prefix('Q')->disabled(),
                    TextInput::make('plazo_meses')->label('Plazo (meses)')->disabled(),
                    TextInput::make('tasa_anual')->label('Tasa anual')->suffix('%')->disabled(),
                    TextInput::make('tasa_mora_anual')->label('Mora anual')->suffix('%')->disabled(),
                    TextInput::make('enganche')->prefix('Q')->disabled(),
                ]),

            Section::make('Seguimiento')
                ->columns(3)
                ->schema([
                    Select::make('estado')->options(PlanPago::ESTADOS)->required()->native(false),
                    Toggle::make('gps_instalado')->label('Tiene GPS'),
                    TextInput::make('gps_referencia')->label('Referencia del GPS')->maxLength(60),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
