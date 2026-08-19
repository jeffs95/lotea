<?php

namespace App\Filament\Resources\GastosCompartidos\Schemas;

use App\Models\CategoriaCosto;
use App\Models\GastoCompartido;
use App\Models\Unidad;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GastoCompartidoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El gasto')
                ->columns(2)
                ->schema([
                    TextInput::make('descripcion')
                        ->label('Descripción')
                        ->required()
                        ->maxLength(160)
                        ->columnSpanFull()
                        ->placeholder("Flete marítimo contenedor 40' MSCU7841203"),

                    Select::make('categoria_costo_id')
                        ->label('Categoría')
                        ->options(fn () => CategoriaCosto::where('activa', true)
                            ->where('prorrateable', true)
                            ->orderBy('orden')
                            ->pluck('nombre', 'id'))
                        ->required()
                        ->native(false)
                        ->helperText('Solo aparecen las categorías marcadas como prorrateables.'),

                    Select::make('proveedor_id')
                        ->label('Proveedor')
                        ->relationship('proveedor', 'nombre')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('moneda')
                        ->options(['GTQ' => 'Quetzales (GTQ)', 'USD' => 'Dólares (USD)'])
                        ->default('GTQ')
                        ->required()
                        ->live()
                        ->native(false),

                    TextInput::make('monto')
                        ->label('Monto total')
                        ->numeric()
                        ->required()
                        ->prefix(fn (callable $get) => $get('moneda') === 'USD' ? '$' : 'Q')
                        ->helperText('El total del documento, no lo que le toca a cada carro.'),

                    TextInput::make('tipo_cambio')
                        ->label('Tipo de cambio')
                        ->numeric()
                        ->step('0.0001')
                        ->visible(fn (callable $get) => $get('moneda') === 'USD'),

                    DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),

                    TextInput::make('documento')->label('Documento')->maxLength(60)->placeholder('BL, factura, póliza'),

                    Toggle::make('es_presupuesto')->label('Es un estimado, no un gasto real'),
                ]),

            Section::make('Cómo se reparte')
                ->columns(2)
                ->schema([
                    Select::make('criterio')
                        ->label('Criterio')
                        ->options(GastoCompartido::CRITERIOS)
                        ->default('partes_iguales')
                        ->required()
                        ->native(false)
                        ->helperText('Por valor le carga más flete al carro más caro.'),

                    CheckboxList::make('unidades')
                        ->label('Unidades que cubre')
                        ->options(fn () => Unidad::enInventario()
                            ->with(['marca', 'linea'])
                            ->orderBy('stock_no')
                            ->get()
                            ->mapWithKeys(fn (Unidad $u) => [$u->id => "{$u->stock_no} · {$u->descripcion}"])
                            ->all())
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->columnSpanFull()
                        ->required()
                        ->minItems(2)
                        ->helperText('El sistema reparte el total y cuadra los centavos.'),
                ]),
        ]);
    }
}
