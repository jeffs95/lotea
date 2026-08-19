<?php

namespace App\Filament\Resources\Empleados\Tables;

use App\Models\Empleado;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmpleadosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sucursal']))
            ->defaultSort('apellidos')
            ->columns([
                TextColumn::make('codigo')->label('Código')->badge()->color('gray')->searchable(),

                TextColumn::make('nombre_completo')
                    ->label('Empleado')
                    ->weight('bold')
                    ->searchable(['nombres', 'apellidos'])
                    ->description(fn (Empleado $record) => $record->puesto),

                TextColumn::make('area')
                    ->label('Área')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Empleado::AREAS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'ventas' => 'success',
                        'taller' => 'warning',
                        'gerencia' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('sucursal.nombre')->label('Sucursal')->placeholder('—')->toggleable(),

                TextColumn::make('fecha_ingreso')
                    ->label('Ingresó')
                    ->date('d/m/Y')
                    ->description(fn (Empleado $record) => $record->antiguedad !== null
                        ? $record->antiguedad.' años'
                        : null)
                    ->sortable(),

                TextColumn::make('ingreso_mensual')
                    ->label('Ingreso mensual')
                    ->money('GTQ', locale: 'es_GT')
                    ->alignEnd()
                    ->visible(fn () => auth()->user()?->can('ver_costos_unidad') ?? false),

                IconColumn::make('es_mecanico')->label('Mecánico')->boolean()->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('estado')
                    ->badge()
                    ->state(fn (Empleado $record) => $record->estaDeBaja() ? 'De baja' : ($record->activo ? 'Activo' : 'Inactivo'))
                    ->color(fn (Empleado $record) => $record->estaDeBaja() ? 'danger' : ($record->activo ? 'success' : 'gray'))
                    ->description(fn (Empleado $record) => $record->motivo_baja),
            ])
            ->filters([
                SelectFilter::make('area')->label('Área')->options(Empleado::AREAS)->multiple(),
                SelectFilter::make('sucursal')->relationship('sucursal', 'nombre'),
                Filter::make('activos')->label('Solo activos')->query(fn ($query) => $query->activos()),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
