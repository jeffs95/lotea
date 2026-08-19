<?php

namespace App\Filament\Widgets;

use App\Models\Unidad;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Las unidades que llevan más tiempo paradas, de la peor a la menos mala.
 *
 * Es la lista de tareas del dueño: cada fila es plata que no se ha movido.
 */
class UnidadesEstancadas extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Unidades más estancadas';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Unidad::enInventario()->orderBy('fecha_compra'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nada estancado')
            ->emptyStateDescription('Ninguna unidad lleva demasiado tiempo en el patio.')
            ->columns([
                TextColumn::make('stock_no')->label('Stock')->weight('bold'),
                TextColumn::make('descripcion')->label('Unidad'),
                TextColumn::make('estado')->label('Estado')->badge(),
                TextColumn::make('dias_inventario')
                    ->label('Días en patio')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 120 => 'danger',
                        $state > 90 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('costo_total')
                    ->label('Capital detenido')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('ver_costos_unidad') ?? false),
            ]);
    }
}
