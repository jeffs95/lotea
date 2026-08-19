<?php

namespace App\Filament\Resources\Unidades\RelationManagers;

use App\Actions\AnularCosto;
use App\Actions\RegistrarCosto;
use App\Models\CategoriaCosto;
use App\Models\CostoUnidad;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los gastos de la unidad.
 *
 * No hay editar ni borrar a propósito: un gasto equivocado se anula con
 * motivo. Si se pudiera borrar, cualquiera podría maquillar el margen de un
 * carro y nadie se daría cuenta.
 */
class CostosRelationManager extends RelationManager
{
    protected static string $relationship = 'costos';

    protected static ?string $title = 'Gastos';

    protected static ?string $modelLabel = 'gasto';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('categoria_costo_id')
                ->label('Categoría')
                ->options(fn () => CategoriaCosto::where('activa', true)
                    ->orderBy('orden')
                    ->get()
                    ->groupBy('grupo')
                    ->map(fn ($grupo) => $grupo->pluck('nombre', 'id'))
                    ->all())
                ->required()
                ->searchable()
                ->native(false),

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
                ->label(fn (callable $get) => $get('moneda') === 'USD' ? 'Monto en dólares' : 'Monto en quetzales')
                ->numeric()
                ->required()
                ->prefix(fn (callable $get) => $get('moneda') === 'USD' ? '$' : 'Q'),

            TextInput::make('tipo_cambio')
                ->label('Tipo de cambio')
                ->numeric()
                ->step('0.0001')
                ->visible(fn (callable $get) => $get('moneda') === 'USD')
                ->helperText('El del documento. Si se deja vacío se usa el de referencia del día.'),

            DatePicker::make('fecha')
                ->required()
                ->default(now())
                ->native(false)
                ->displayFormat('d/m/Y'),

            TextInput::make('documento')
                ->label('Documento')
                ->maxLength(60)
                ->placeholder('Factura, DUA, BL, lote'),

            Toggle::make('es_presupuesto')
                ->label('Es un estimado, no un gasto real')
                ->helperText('Marcalo para el landed cost que calculás antes de pujar.'),

            Textarea::make('descripcion')
                ->label('Descripción')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['categoria', 'proveedor']))
            ->recordTitleAttribute('descripcion')
            ->defaultSort('fecha')
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),

                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->description(fn (CostoUnidad $record) => collect([
                        $record->proveedor?->nombre,
                        $record->documento,
                    ])->filter()->implode(' · ') ?: null)
                    ->searchable(),

                TextColumn::make('monto')
                    ->label('Monto original')
                    ->alignEnd()
                    ->formatStateUsing(fn (CostoUnidad $record) => $record->moneda === 'USD'
                        ? '$ '.number_format((float) $record->monto, 2).' × '.rtrim(rtrim(number_format((float) $record->tipo_cambio, 4), '0'), '.')
                        : '—')
                    ->color('gray'),

                TextColumn::make('monto_base')
                    ->label('En quetzales')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium')
                    ->color(fn (CostoUnidad $record) => $record->estaAnulado() ? 'gray' : null),

                IconColumn::make('es_presupuesto')
                    ->label('Estimado')
                    ->boolean()
                    ->trueIcon('heroicon-o-calculator')
                    ->falseIcon('heroicon-o-banknotes')
                    ->trueColor('warning')
                    ->falseColor('success'),

                TextColumn::make('anulado_en')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (CostoUnidad $record) => $record->estaAnulado() ? 'Anulado' : 'Vigente')
                    ->color(fn (CostoUnidad $record) => $record->estaAnulado() ? 'danger' : 'success')
                    ->description(fn (CostoUnidad $record) => $record->motivo_anulacion),
            ])
            ->filters([
                TernaryFilter::make('es_presupuesto')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->trueLabel('Solo estimados')
                    ->falseLabel('Solo gastos reales'),

                TernaryFilter::make('anulado_en')
                    ->label('Vigencia')
                    ->placeholder('Todos')
                    ->trueLabel('Solo anulados')
                    ->falseLabel('Solo vigentes')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('anulado_en'),
                        false: fn ($query) => $query->whereNull('anulado_en'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Registrar gasto')
                    ->using(fn (array $data) => app(RegistrarCosto::class)->ejecutar($this->getOwnerRecord(), $data)),
            ])
            ->recordActions([
                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CostoUnidad $record) => ! $record->estaAnulado())
                    ->schema([
                        Textarea::make('motivo')
                            ->label('¿Por qué se anula?')
                            ->required()
                            ->rows(2)
                            ->helperText('Queda registrado con tu nombre y la fecha.'),
                    ])
                    ->action(function (CostoUnidad $record, array $data) {
                        try {
                            app(AnularCosto::class)->ejecutar($record, $data['motivo']);

                            Notification::make()
                                ->title('Gasto anulado')
                                ->body('El costo de la unidad se recalculó.')
                                ->success()
                                ->send();
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
