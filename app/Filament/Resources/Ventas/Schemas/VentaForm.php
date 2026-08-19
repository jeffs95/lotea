<?php

namespace App\Filament\Resources\Ventas\Schemas;

use App\Actions\GenerarNumeroVenta;
use App\Models\Unidad;
use App\Models\Venta;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VentaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Qué se vende y a quién')
                ->columns(2)
                ->schema([
                    Select::make('unidad_id')
                        ->label('Unidad')
                        ->options(fn ($record) => Unidad::query()
                            ->when(! $record, fn ($q) => $q->enInventario())
                            ->with(['marca', 'linea'])
                            ->orderBy('stock_no')
                            ->get()
                            ->mapWithKeys(fn (Unidad $u) => [$u->id => "{$u->stock_no} · {$u->descripcion}"])
                            ->all())
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Unidad::query()
                            ->enInventario()
                            ->where(fn ($q) => $q
                                ->where('stock_no', 'ilike', "%{$search}%")
                                ->orWhere('placa', 'ilike', "%{$search}%")
                                ->orWhere('codigo_qr', 'ilike', '%'.\App\Support\CodigoDeUnidad::normalizar($search).'%')
                                ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'ilike', "%{$search}%"))
                                ->orWhereHas('linea', fn ($l) => $l->where('nombre', 'ilike', "%{$search}%")))
                            ->with(['marca', 'linea'])
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Unidad $u) => [$u->id => "{$u->stock_no} · {$u->descripcion}"])
                            ->all())
                        ->getOptionLabelUsing(fn ($value) => ($u = Unidad::find($value))
                            ? "{$u->stock_no} · {$u->descripcion}"
                            : null)
                        ->live()
                        ->disabled(fn ($record) => $record?->estaCerrada())
                        ->native(false)
                        ->helperText('Podés buscar por stock, marca o el código del parabrisas.')
                        // Llega precargada cuando se entra desde el QR o desde
                        // la ficha de la unidad.
                        ->default(fn () => request()->integer('unidad') ?: null)
                        ->afterStateUpdated(function ($state, callable $set) {
                            $unidad = Unidad::find($state);
                            $set('precio_venta', $unidad?->precio_lista);
                            $set('sucursal_id', $unidad?->sucursal_id);
                        }),

                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->relationship('cliente', 'nombre')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->createOptionForm([
                            TextInput::make('nombre')->required()->maxLength(160),
                            TextInput::make('nit')->label('NIT')->maxLength(20),
                            TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                        ]),

                    Select::make('vendedor_id')
                        ->label('Vendedor')
                        ->relationship('vendedor', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('sucursal_id')
                        ->label('Sucursal')
                        ->relationship('sucursal', 'nombre')
                        ->native(false)
                        ->default(fn () => Unidad::find(request()->integer('unidad'))?->sucursal_id),

                    TextInput::make('numero')
                        ->label('No.')
                        ->required()
                        ->default(fn () => app(GenerarNumeroVenta::class)->ejecutar())
                        ->maxLength(20),

                    DatePicker::make('fecha')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                ]),

            Section::make('El trato')
                ->columns(3)
                ->schema([
                    Select::make('estado')
                        ->options(Venta::ESTADOS)
                        ->default('cotizacion')
                        ->required()
                        ->live()
                        ->native(false)
                        ->disabled(fn ($record) => $record?->estaAnulada())
                        ->helperText('Al pasar a «Cerrada» se marca la unidad como vendida.'),

                    TextInput::make('precio_venta')
                        ->label('Precio pactado')
                        ->numeric()
                        ->required()
                        ->prefix('Q')
                        ->live(onBlur: true)
                        ->default(fn () => Unidad::find(request()->integer('unidad'))?->precio_lista),
                    TextInput::make('descuento')->label('Descuento')->numeric()->default(0)->prefix('Q')->live(onBlur: true),

                    Select::make('forma_pago')
                        ->label('Forma de pago')
                        ->options(Venta::FORMAS_PAGO)
                        ->default('contado')
                        ->required()
                        ->live()
                        ->native(false),

                    TextInput::make('enganche')
                        ->numeric()
                        ->prefix('Q')
                        ->visible(fn (callable $get) => in_array($get('forma_pago'), ['financiamiento_banco', 'credito_propio', 'mixto'], true)),

                    TextInput::make('saldo_financiado')
                        ->label('Saldo financiado')
                        ->numeric()
                        ->prefix('Q')
                        ->visible(fn (callable $get) => in_array($get('forma_pago'), ['financiamiento_banco', 'credito_propio', 'mixto'], true)),

                    TextInput::make('deposito')
                        ->label('Depósito de reserva')
                        ->numeric()
                        ->prefix('Q')
                        ->visible(fn (callable $get) => $get('estado') === 'reservada'),

                    DatePicker::make('deposito_vence_en')
                        ->label('El apartado vence')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn (callable $get) => $get('estado') === 'reservada'),
                ]),

            Section::make('Comisión')
                ->columns(3)
                ->schema([
                    Select::make('comision_base')
                        ->label('Se calcula sobre')
                        ->options(Venta::BASES_COMISION)
                        ->default('margen')
                        ->required()
                        ->native(false)
                        ->helperText('Sobre la utilidad alinea al vendedor con el dueño: si regala precio, se corta su comisión.'),

                    TextInput::make('comision_porcentaje')->label('Porcentaje')->numeric()->default(0)->suffix('%')->step('0.001'),

                    Toggle::make('comision_pagada')->label('Ya se pagó'),
                ]),

            Section::make('Factura y entrega')
                ->columns(4)
                ->collapsed()
                ->schema([
                    TextInput::make('factura_serie')->label('Serie')->maxLength(30),
                    TextInput::make('factura_numero')->label('Número')->maxLength(30),
                    TextInput::make('factura_uuid')->label('UUID')->maxLength(60),
                    DatePicker::make('factura_fecha')->label('Fecha')->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('entregada_en')->label('Entregada el')->native(false)->displayFormat('d/m/Y'),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
