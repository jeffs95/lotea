<?php

namespace App\Filament\Resources\OrdenesTrabajo\RelationManagers;

use App\Actions\AgregarLineaOrdenTrabajo;
use App\Actions\RecalcularOrdenTrabajo;
use App\Models\Empleado;
use App\Models\OtLinea;
use DomainException;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** El trabajo de la orden: horas, repuestos y lo que se mandó afuera. */
class LineasRelationManager extends RelationManager
{
    protected static string $relationship = 'lineas';

    protected static ?string $title = 'Trabajo realizado';

    protected static ?string $modelLabel = 'línea';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('tipo')
                ->options(OtLinea::TIPOS)
                ->default('mano_obra')
                ->required()
                ->live()
                ->native(false),

            TextInput::make('descripcion')
                ->label('Descripción')
                ->required()
                ->maxLength(160)
                ->placeholder('Enderezado de guardafango delantero'),

            Select::make('empleado_id')
                ->label('Mecánico')
                ->options(fn () => Empleado::mecanicos()
                    ->selectRaw("id, trim(nombres || ' ' || apellidos) as nombre")
                    ->orderBy('nombres')
                    ->pluck('nombre', 'id'))
                ->searchable()
                ->native(false)
                ->live()
                ->visible(fn (callable $get) => $get('tipo') === 'mano_obra')
                ->afterStateUpdated(fn ($state, callable $set) => $set('costo_unitario', Empleado::find($state)?->costo_hora))
                ->helperText('Su costo por hora se toma solo.'),

            Select::make('proveedor_id')
                ->label('Proveedor')
                ->relationship('proveedor', 'nombre')
                ->searchable()
                ->preload()
                ->native(false)
                ->visible(fn (callable $get) => in_array($get('tipo'), ['repuesto', 'tercero'], true)),

            TextInput::make('cantidad')
                ->label(fn (callable $get) => $get('tipo') === 'mano_obra' ? 'Horas' : 'Cantidad')
                ->numeric()
                ->default(1)
                ->required()
                ->live(onBlur: true),

            TextInput::make('costo_unitario')
                ->label(fn (callable $get) => $get('tipo') === 'mano_obra' ? 'Costo por hora' : 'Costo unitario')
                ->numeric()
                ->required()
                ->prefix('Q')
                ->live(onBlur: true),

            TextInput::make('documento')->label('Documento')->maxLength(60)->placeholder('Factura del repuesto'),

            Select::make('estado')
                ->options(['pendiente' => 'Pendiente', 'hecha' => 'Hecha'])
                ->default('pendiente')
                ->required()
                ->native(false)
                ->visible(fn (callable $get) => $get('tipo') === 'mano_obra'),

            Textarea::make('notas')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['empleado', 'proveedor']))
            ->recordTitleAttribute('descripcion')
            ->defaultGroup('tipo')
            ->columns([
                TextColumn::make('descripcion')
                    ->label('Trabajo')
                    ->wrap()
                    ->description(fn (OtLinea $record) => collect([
                        $record->empleado?->nombre_completo,
                        $record->proveedor?->nombre,
                        $record->documento,
                    ])->filter()->implode(' · ')),

                TextColumn::make('cantidad')
                    ->label('Cant.')
                    ->alignEnd()
                    ->formatStateUsing(fn (OtLinea $record) => rtrim(rtrim(number_format((float) $record->cantidad, 2), '0'), '.')
                        .' '.$record->unidad_cantidad),

                TextColumn::make('costo_unitario')->label('Unitario')->money('GTQ', locale: 'es_GT')->alignEnd(),

                TextColumn::make('total')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->weight('medium')
                    ->summarize(Sum::make()->label('Total')->money('GTQ', locale: 'es_GT')),

                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'hecha' ? 'Hecha' : 'Pendiente')
                    ->color(fn (string $state) => $state === 'hecha' ? 'success' : 'warning')
                    ->visible(fn ($livewire) => true),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(OtLinea::TIPOS),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar trabajo')
                    ->visible(fn () => $this->getOwnerRecord()->admiteCambios())
                    ->using(function (array $data) {
                        try {
                            return app(AgregarLineaOrdenTrabajo::class)->ejecutar($this->getOwnerRecord(), $data);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            throw $e;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->admiteCambios())
                    ->using(function (OtLinea $record, array $data) {
                        $data['total'] = bcmul((string) $data['cantidad'], (string) $data['costo_unitario'], 2);
                        $record->update($data);

                        app(RecalcularOrdenTrabajo::class)->ejecutar($this->getOwnerRecord());

                        return $record;
                    }),

                DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->admiteCambios())
                    ->after(fn () => app(RecalcularOrdenTrabajo::class)->ejecutar($this->getOwnerRecord())),
            ])
            ->toolbarActions([]);
    }
}
