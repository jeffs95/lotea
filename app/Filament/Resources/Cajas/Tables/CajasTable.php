<?php

namespace App\Filament\Resources\Cajas\Tables;

use App\Filament\Resources\Cajas\Actions\AccionesDeCaja;
use App\Models\Caja;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CajasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Precarga: sin esto cada fila dispara una consulta por
            // relación, y con doscientas filas son cientos de consultas.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sucursal', 'arqueos']))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Caja $record) => $record->tipo === 'banco'
                        ? trim($record->banco.' '.$record->numero_cuenta)
                        : null),

                TextColumn::make('sucursal.nombre')->label('Sucursal')->placeholder('—')->toggleable(),

                TextColumn::make('tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => Caja::TIPOS[$state] ?? $state),

                TextColumn::make('moneda')->badge()->color(fn (Caja $record) => $record->esEnDolares() ? 'info' : 'gray'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->alignEnd()
                    ->weight('bold')
                    ->formatStateUsing(fn (Caja $record) => ($record->esEnDolares() ? '$ ' : 'Q ').number_format((float) $record->saldo, 2))
                    ->color(fn (Caja $record) => bccomp((string) $record->saldo, '0.00', 2) < 0 ? 'danger' : null),

                TextColumn::make('ultimo_arqueo')
                    ->label('Último arqueo')
                    ->state(fn (Caja $record) => $record->arqueos()->value('realizado_en'))
                    ->since()
                    ->placeholder('Nunca')
                    ->color(fn ($state) => $state === null ? 'warning' : null)
                    ->toggleable(),

                IconColumn::make('activa')->boolean(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(Caja::TIPOS),
                SelectFilter::make('sucursal')->relationship('sucursal', 'nombre'),
            ])
            ->recordActions([
                AccionesDeCaja::registrarMovimiento(),
                ActionGroup::make([
                    AccionesDeCaja::trasladar(),
                    AccionesDeCaja::arquear(),
                    EditAction::make(),
                ]),
            ]);
    }
}
