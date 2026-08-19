<?php

namespace App\Filament\Resources\Unidades\Schemas;

use App\Actions\GenerarStockNo;
use App\Enums\EstadoUnidad;
use App\Enums\TipoPlaca;
use App\Enums\TipoVehiculo;
use App\Models\Linea;
use App\Support\QrDeUnidad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class UnidadForm
{
    /** ¿El estado elegido implica que la unidad ya está físicamente aquí? */
    protected static function yaEstaEnElPatio(callable $get): bool
    {
        $estado = EstadoUnidad::tryFrom((string) $get('estado'));

        return $estado !== null && in_array($estado->etapa(), ['preparacion', 'venta'], true);
    }

    /** El tipo elegido en el formulario, o automóvil mientras no se elija. */
    protected static function tipo(callable $get): TipoVehiculo
    {
        return TipoVehiculo::tryFrom((string) $get('tipo_vehiculo')) ?? TipoVehiculo::Automovil;
    }

    /** Todas las de todos los tipos: el formulario filtra según corresponda. */
    public const TRANSMISIONES = [
        'automatica' => 'Automática',
        'manual' => 'Manual',
        'cvt' => 'CVT',
        'semiautomatica' => 'Semiautomática',
    ];

    public const COMBUSTIBLES = ['gasolina' => 'Gasolina', 'diesel' => 'Diésel', 'hibrido' => 'Híbrido', 'electrico' => 'Eléctrico'];

    public const TRACCIONES = ['4x2' => '4x2', '4x4' => '4x4', 'awd' => 'AWD'];

    public const TIPOS_TITULO = ['clean' => 'Clean', 'salvage' => 'Salvage', 'rebuilt' => 'Rebuilt'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Identificación')->schema([
                    Section::make()->columns(3)->schema([
                        TextInput::make('vin')
                            ->label('VIN')
                            ->required()
                            ->length(17)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->dehydrateStateUsing(fn (?string $state) => Str::upper(trim($state ?? '')))
                            ->scopedUnique(ignoreRecord: true)
                            ->helperText('17 caracteres. Es la identidad del carro; no se cambia después.'),

                        TextInput::make('stock_no')
                            ->label('No. de stock')
                            ->required()
                            ->maxLength(20)
                            ->scopedUnique(ignoreRecord: true)
                            ->default(fn () => app(GenerarStockNo::class)->ejecutar())
                            ->helperText('Como lo llaman en el patio y en WhatsApp.'),

                        Select::make('sucursal_id')
                            ->label('Sucursal')
                            ->relationship('sucursal', 'nombre')
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ]),

                    Section::make('Placa')
                        ->description('Se llena cuando la unidad se nacionaliza. Una que viene de subasta todavía no tiene.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('placa')
                                ->maxLength(20)
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->dehydrateStateUsing(fn (?string $state) => filled($state)
                                    ? Str::upper(Str::squish($state))
                                    : null)
                                ->live(onBlur: true)
                                ->placeholder('P123ABC')
                                // El tipo sale de la letra inicial: nadie debería
                                // tener que elegirlo a mano.
                                ->afterStateUpdated(fn (?string $state, callable $set) => $set(
                                    'tipo_placa',
                                    TipoPlaca::desdeLaPlaca($state)?->value,
                                )),

                            Select::make('tipo_placa')
                                ->label('Tipo de placa')
                                ->options(TipoPlaca::opciones())
                                ->native(false)
                                ->helperText('Se deduce de la letra inicial. El uso inscrito cambia el precio de reventa.'),
                        ]),

                    Section::make('Código para el parabrisas')
                        ->description('El mismo QR lleva al cliente a la ficha pública y a ustedes a esta pantalla.')
                        ->columns(2)
                        ->visibleOn('edit')
                        ->schema([
                            TextInput::make('codigo_qr')
                                ->label('Código')
                                ->disabled()
                                ->extraInputAttributes(['style' => 'font-family: ui-monospace, monospace; letter-spacing: .2em; font-weight: 700']),

                            Placeholder::make('qr')
                                ->hiddenLabel()
                                ->content(fn ($record) => $record
                                    ? new HtmlString(
                                        '<img src="'.QrDeUnidad::dataUri($record, 140).'" alt="QR" style="height:140px;width:140px">'
                                    )
                                    : null),
                        ]),

                    Section::make()->columns(4)->schema([
                        Select::make('tipo_vehiculo')
                            ->label('Tipo de vehículo')
                            ->options(TipoVehiculo::opciones())
                            ->default(TipoVehiculo::Automovil->value)
                            ->required()
                            ->live()
                            ->native(false)
                            ->columnSpan(1)
                            ->helperText('Cambia la ficha técnica.')
                            ->afterStateUpdated(function (callable $set) {
                                // Lo que no aplica al nuevo tipo se limpia en
                                // vez de quedar escondido con un valor viejo.
                                $set('carroceria', null);
                                $set('transmision', null);
                                $set('puertas', null);
                                $set('color_interior', null);
                                $set('traccion', null);
                            }),

                        Select::make('marca_id')
                            ->label('Marca')
                            ->relationship('marca', 'nombre')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(fn (callable $set) => $set('linea_id', null)),

                        Select::make('linea_id')
                            ->label('Línea')
                            ->options(fn (callable $get) => $get('marca_id')
                                ? Linea::where('marca_id', $get('marca_id'))->orderBy('nombre')->pluck('nombre', 'id')
                                : [])
                            ->searchable()
                            ->native(false)
                            ->disabled(fn (callable $get) => ! $get('marca_id')),

                        TextInput::make('version')->label('Versión')->maxLength(60)->placeholder('XLE, Sport, Limited'),

                        TextInput::make('anio')
                            ->label('Año')
                            ->required()
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 2),
                    ]),
                ]),

                Tab::make('Ficha técnica')->schema([
                    Section::make()->columns(4)->schema([
                        Select::make('carroceria')
                            ->label(fn (callable $get) => self::tipo($get)->esMoto() ? 'Tipo de moto' : 'Carrocería')
                            ->options(fn (callable $get) => self::tipo($get)->carrocerias())
                            ->native(false),

                        Select::make('transmision')
                            ->label('Transmisión')
                            ->options(fn (callable $get) => self::tipo($get)->transmisiones())
                            ->native(false),

                        Select::make('combustible')->options(self::COMBUSTIBLES)->native(false),

                        Select::make('traccion')
                            ->label('Tracción')
                            ->options(self::TRACCIONES)
                            ->native(false)
                            ->visible(fn (callable $get) => self::tipo($get)->aplica('traccion')),

                        TextInput::make('motor')
                            ->maxLength(40)
                            ->placeholder(fn (callable $get) => self::tipo($get)->esMoto() ? '4T refrigerado por aire' : '2.5L L4'),

                        TextInput::make('cilindrada_cc')
                            ->label('Cilindrada (cc)')
                            ->numeric()
                            ->minValue(49)
                            ->maxValue(9999)
                            ->suffix('cc')
                            ->helperText(fn (callable $get) => self::tipo($get)->destacaCilindrada()
                                ? 'Es lo primero que pregunta el cliente.'
                                : null),

                        TextInput::make('cilindros')->numeric()->minValue(1)->maxValue(16),

                        TextInput::make('puertas')
                            ->numeric()
                            ->minValue(2)
                            ->maxValue(6)
                            ->visible(fn (callable $get) => self::tipo($get)->aplica('puertas')),

                        TextInput::make('color')->maxLength(40),

                        TextInput::make('color_interior')
                            ->label('Color interior')
                            ->maxLength(40)
                            ->visible(fn (callable $get) => self::tipo($get)->aplica('color_interior')),
                        TextInput::make('odometro')
                            ->label('Odómetro')
                            ->numeric(),
                        Select::make('odometro_unidad')
                            ->label('Unidad')
                            ->options(['mi' => 'Millas', 'km' => 'Kilómetros'])
                            ->default('mi')
                            ->native(false)
                            ->helperText('De subasta viene en millas.'),
                    ]),
                ]),

                Tab::make('Origen y título')->schema([
                    Section::make('¿En qué punto entra al sistema?')
                        ->description('No todos los carros se registran al comprarlos: puede que ya lo tengas en el patio, o que estés cargando el inventario que ya tenías.')
                        ->columns(2)
                        ->visibleOn('create')
                        ->schema([
                            Select::make('estado')
                                ->label('Estado actual de la unidad')
                                ->options(fn () => collect(EstadoUnidad::cases())
                                    ->filter(fn (EstadoUnidad $e) => $e->esInventario())
                                    ->mapWithKeys(fn (EstadoUnidad $e) => [$e->value => $e->getLabel()])
                                    ->all())
                                ->default(EstadoUnidad::Comprada->value)
                                ->required()
                                ->live()
                                ->native(false)
                                ->helperText('El historial arranca aquí: las etapas anteriores no quedan registradas.'),

                            DatePicker::make('fecha_recepcion')
                                ->label('Llegó al patio el')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->visible(fn (callable $get) => self::yaEstaEnElPatio($get))
                                ->helperText('Con esto se calculan sus días en el patio.'),
                        ]),

                    Section::make()->columns(3)->schema([
                        Select::make('tipo_titulo')
                            ->label('Tipo de título')
                            ->options(self::TIPOS_TITULO)
                            ->native(false)
                            ->helperText('Define el precio de reventa y a quién se le puede vender.'),
                        TextInput::make('tipo_dano')->label('Tipo de daño')->maxLength(80)->placeholder('Front end, Water/flood, Hail'),
                        Toggle::make('tiene_llaves')->label('Viene con llaves')->default(true),
                        DatePicker::make('fecha_compra')->label('Fecha de compra')->native(false)->displayFormat('d/m/Y'),
                    ]),

                    Section::make('Fotos de subasta')
                        ->description('Cómo venía el carro según el anuncio. Son la prueba para reclamar si llega distinto.')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('fotos_subasta')
                                ->collection('fotos_subasta')
                                ->multiple()
                                ->reorderable()
                                ->image()
                                ->imageEditor()
                                ->panelLayout('grid')
                                ->hiddenLabel(),
                        ]),
                ]),

                Tab::make('Comercial')->schema([
                    Section::make()->columns(3)->schema([
                        TextInput::make('precio_lista')
                            ->label('Precio de lista')
                            ->numeric()
                            ->prefix('Q'),
                        TextInput::make('precio_minimo')
                            ->label('Precio mínimo')
                            ->numeric()
                            ->prefix('Q')
                            ->helperText('El piso autorizado. El vendedor no lo ve.'),
                        TextInput::make('ubicacion')->label('Ubicación en el patio')->maxLength(60),
                        Toggle::make('publicado')->label('Publicado en el portal'),
                        Toggle::make('destacado')->label('Destacado'),
                    ]),

                    Textarea::make('descripcion_comercial')
                        ->label('Descripción para el portal')
                        ->rows(4)
                        ->columnSpanFull(),

                    Textarea::make('notas')
                        ->label('Notas internas')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('No se publican.'),
                ]),

                Tab::make('Fotos y documentos')->schema([
                    Section::make('Fotos del patio')
                        ->description('Las que ve el cliente en el portal. La primera es la portada.')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('fotos')
                                ->collection('fotos')
                                ->multiple()
                                ->reorderable()
                                ->image()
                                ->imageEditor()
                                ->panelLayout('grid')
                                ->hiddenLabel(),
                        ]),

                    Section::make('Documentos')
                        ->description('Título, DUA, póliza, factura de compra.')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('documentos')
                                ->collection('documentos')
                                ->multiple()
                                ->hiddenLabel(),
                        ]),
                ]),
            ]),
        ]);
    }
}
