<?php

namespace App\Filament\Resources\OrdenesTrabajo\Schemas;

use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\Unidad;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrdenTrabajoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('La orden')
                ->columns(3)
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
                        ->disabled(fn ($record) => $record?->estaCerrada())
                        ->native(false),

                    TextInput::make('numero')
                        ->label('No.')
                        ->required()
                        ->maxLength(20)
                        ->default(fn () => 'OT-'.str_pad((string) (OrdenTrabajo::where('empresa_id', Tenancy::empresaId())->count() + 1), 4, '0', STR_PAD_LEFT))
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated(),

                    Select::make('tipo')->options(OrdenTrabajo::TIPOS)->default('preparacion')->required()->native(false),

                    Select::make('jefe_id')
                        ->label('Responsable')
                        ->options(fn () => Empleado::activos()
                            ->where('area', 'taller')
                            ->selectRaw("id, trim(nombres || ' ' || apellidos) as nombre")
                            ->orderBy('nombres')
                            ->pluck('nombre', 'id'))
                        ->searchable()
                        ->native(false),

                    Select::make('sucursal_id')->label('Sucursal')->relationship('sucursal', 'nombre')->native(false),

                    Select::make('estado')
                        ->options(OrdenTrabajo::ESTADOS)
                        ->default('abierta')
                        ->required()
                        ->native(false)
                        ->disabled(fn ($record) => $record?->estaCerrada())
                        ->helperText('Cerrarla se hace con el botón, para que descargue el costo.'),

                    DatePicker::make('abierta_en')->label('Abierta el')->required()->default(now())->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('terminada_en')->label('Terminada el')->native(false)->displayFormat('d/m/Y'),
                ]),

            Section::make('Detalle')
                ->schema([
                    Textarea::make('diagnostico')->label('Diagnóstico')->rows(3)->placeholder('Qué trae el carro y qué hay que hacerle'),
                    Textarea::make('notas')->rows(2),
                ]),
        ]);
    }
}
