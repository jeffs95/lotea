<?php

namespace App\Filament\Pages;

use App\Actions\GenerarStockNo;
use App\Enums\EstadoUnidad;
use App\Enums\TipoPlaca;
use App\Enums\TipoVehiculo;
use App\Filament\Resources\Unidades\Actions\LeerDocumentoAction;
use App\Filament\Resources\Unidades\Pages\EtiquetasUnidades;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Linea;
use App\Models\Marca;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\UnidadTransicion;
use App\Support\LimiteDeSubida;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Para levantar el inventario caminando el patio, con el celular en la mano.
 *
 * El alta normal tiene cinco pestañas y treinta campos: es correcta en el
 * escritorio e imposible frente a un carro. Aquí se pide lo mínimo —lo que un
 * comprador quiere ver— y el resto se completa después.
 */
class Levantamiento extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'levantamiento';

    protected static ?string $navigationLabel = 'Levantar inventario';

    protected static ?string $title = 'Levantar inventario';

    protected string $view = 'filament.pages.levantamiento';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** La sucursal se elige una vez: quien camina el patio está en un patio. */
    public ?int $sucursalId = null;

    /** Lo capturado en esta sesión, para ver el avance sin salir de la pantalla. */
    public array $capturadas = [];

    public function mount(): void
    {
        $this->sucursalId = Sucursal::activas()->value('id');

        $this->form->fill($this->valoresIniciales());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('El carro')
                    ->columns(2)
                    ->schema([
                        Select::make('tipo_vehiculo')
                            ->label('Tipo')
                            ->options(TipoVehiculo::opciones())
                            ->default(TipoVehiculo::Automovil->value)
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('marca_id')
                            ->label('Marca')
                            // options() y no relationship(): esta página no
                            // tiene un registro del cual colgar la relación.
                            ->options(fn () => Marca::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
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

                        TextInput::make('anio')
                            ->label('Año')
                            ->numeric()
                            ->minValue(1980)
                            ->maxValue((int) date('Y') + 2),

                        TextInput::make('precio_lista')
                            ->label('Precio de venta')
                            ->numeric()
                            ->required()
                            ->prefix('Q')
                            ->helperText('Es lo que el cliente va a buscar.'),

                        TextInput::make('placa')
                            ->maxLength(20)
                            ->placeholder('P123ABC')
                            ->helperText('Si ya está nacionalizado.'),

                        TextInput::make('vin')
                            ->label('VIN')
                            ->length(17)
                            ->columnSpanFull()
                            ->helperText('Si no lo tenés a mano, seguí sin él y se completa después.'),
                    ]),

                Section::make('Fotos')
                    ->description('Con estas se publica. Tres o cuatro alcanzan: frente, lateral, interior y tablero.')
                    ->schema([
                        // FileUpload normal y no el de medialibrary: aquí
                        // todavía no existe la unidad a la que colgarlas, y se
                        // adjuntan al guardar.
                        FileUpload::make('fotos')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxFiles(12)
                            ->maxSize(LimiteDeSubida::KILOBYTES)
                            ->disk('local')
                            ->directory('levantamiento')
                            ->visibility('private')
                            ->panelLayout('grid')
                            ->hiddenLabel()
                            ->required(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            LeerDocumentoAction::make()->label('Leer documento'),

            Action::make('etiquetas')
                ->label('Imprimir etiquetas de esta sesión')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->visible(fn () => filled($this->capturadas))
                ->url(fn () => EtiquetasUnidades::getUrl().'?unidades='.implode(',', array_column($this->capturadas, 'id')))
                ->openUrlInNewTab(),
        ];
    }

    /** @return Collection<int, Sucursal> */
    public function getSucursales(): Collection
    {
        return Sucursal::activas()->orderBy('nombre')->get();
    }

    public function guardarYSeguir(): void
    {
        $datos = $this->form->getState();

        $unidad = Unidad::create([
            'sucursal_id' => $this->sucursalId,
            'stock_no' => app(GenerarStockNo::class)->ejecutar(),
            'tipo_vehiculo' => $datos['tipo_vehiculo'],
            'marca_id' => $datos['marca_id'],
            'linea_id' => $datos['linea_id'] ?? null,
            'anio' => $datos['anio'] ?? null,
            'vin' => filled($datos['vin'] ?? null) ? strtoupper($datos['vin']) : null,
            'placa' => filled($datos['placa'] ?? null) ? strtoupper($datos['placa']) : null,
            'tipo_placa' => TipoPlaca::desdeLaPlaca($datos['placa'] ?? null)?->value,
            'precio_lista' => $datos['precio_lista'],
            // Se levanta lo que ya está en el patio y en venta.
            'estado' => EstadoUnidad::Lista,
            'estado_desde' => now(),
            'fecha_recepcion' => now()->toDateString(),
            'fecha_lista' => now()->toDateString(),
        ]);

        $this->adjuntarFotos($unidad, $datos['fotos'] ?? []);

        // Con foto y precio ya cumple, así que se publica sola.
        $unidad->refresh()->update(['publicado' => $unidad->puedePublicarse()]);

        UnidadTransicion::create([
            'unidad_id' => $unidad->id,
            'user_id' => auth()->id(),
            'estado_anterior' => null,
            'estado_nuevo' => $unidad->estado,
            'ocurrio_en' => now(),
            'nota' => 'Levantamiento de inventario',
        ]);

        $this->capturadas[] = [
            'id' => $unidad->id,
            'stock_no' => $unidad->stock_no,
            'descripcion' => $unidad->descripcion,
            'precio' => (float) $unidad->precio_lista,
            'publicada' => $unidad->publicado,
            'falta' => $unidad->loQueFalta(),
            'url' => UnidadResource::getUrl('edit', ['record' => $unidad]),
        ];

        Notification::make()
            ->title("{$unidad->stock_no} capturado")
            ->body($unidad->estaCompleta()
                ? 'Ficha completa y publicada en el portal.'
                : 'Publicada. Falta completar: '.implode(', ', $unidad->loQueFalta()).'.')
            ->success()
            ->send();

        // Formulario limpio para el siguiente carro, sin recargar la pantalla.
        $this->form->fill($this->valoresIniciales());
    }

    /**
     * Pasa las fotos subidas a la galería de la unidad y limpia los temporales.
     *
     * @param  array<string, string>|array<int, string>  $rutas
     */
    protected function adjuntarFotos(Unidad $unidad, array $rutas): void
    {
        foreach (array_values($rutas) as $ruta) {
            if (! is_string($ruta) || ! Storage::disk('local')->exists($ruta)) {
                continue;
            }

            $unidad->addMediaFromDisk($ruta, 'local')->toMediaCollection('fotos');
        }
    }

    /** @return array<string, mixed> */
    protected function valoresIniciales(): array
    {
        return [
            'tipo_vehiculo' => TipoVehiculo::Automovil->value,
            'marca_id' => null,
            'linea_id' => null,
            'anio' => null,
            'vin' => null,
            'placa' => null,
            'precio_lista' => null,
            'fotos' => [],
        ];
    }
}
