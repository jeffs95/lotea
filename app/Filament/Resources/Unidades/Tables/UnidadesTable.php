<?php

namespace App\Filament\Resources\Unidades\Tables;

use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\Pages\EtiquetasUnidades;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UnidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('foto')
                    ->label('')
                    ->collection('fotos')
                    ->conversion('miniatura')
                    ->limit(1)
                    ->height(44),

                TextColumn::make('stock_no')
                    ->label('Stock')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tipo_vehiculo')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('descripcion')
                    ->label('Unidad')
                    ->description(fn ($record) => $record->vin)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where('vin', 'ilike', "%{$search}%")
                        ->orWhere('version', 'ilike', "%{$search}%")),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                // El semáforo del capital dormido: rojo es plata parada.
                TextColumn::make('dias_en_estado')
                    ->label('Días aquí')
                    ->badge()
                    ->alignCenter()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 30 => 'danger',
                        $state > 15 => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : $state),

                TextColumn::make('dias_inventario')
                    ->label('Días en patio')
                    ->alignCenter()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 120 => 'danger',
                        $state > 90 => 'warning',
                        default => null,
                    })
                    ->toggleable(),

                TextColumn::make('sucursal.nombre')->label('Sucursal')->toggleable()->sortable(),

                TextColumn::make('costo_total')
                    ->label('Costo')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->sortable()
                    // El vendedor nunca ve el costo. Es requisito de negocio:
                    // si se filtra, el dueño pierde poder de negociación.
                    ->visible(fn () => auth()->user()?->can('ver_costos_unidad') ?? false),

                TextColumn::make('precio_lista')
                    ->label('Precio')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(EstadoUnidad::opciones())
                    ->multiple(),
                SelectFilter::make('sucursal')->relationship('sucursal', 'nombre'),
                SelectFilter::make('marca')->relationship('marca', 'nombre')->searchable()->preload(),
                SelectFilter::make('tipo_vehiculo')->label('Tipo')->options(\App\Enums\TipoVehiculo::opciones()),
                SelectFilter::make('tipo_titulo')->label('Título')->options(UnidadForm::TIPOS_TITULO),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    \App\Filament\Resources\Unidades\Actions\CambiarEstadoAction::make(),

                    Action::make('etiqueta')
                        ->label('Imprimir etiqueta')
                        ->icon('heroicon-o-qr-code')
                        ->url(fn ($record) => EtiquetasUnidades::getUrl().'?unidades='.$record->id)
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('etiquetas')
                    ->label('Imprimir etiquetas')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn ($records) => redirect()->to(
                        EtiquetasUnidades::getUrl().'?unidades='.$records->pluck('id')->implode(',')
                    )),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
