<?php

namespace App\Filament\Central\Resources\Planes\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public const MODULOS = [
        'unidades' => 'Unidades e inventario',
        'importacion' => 'Ciclo de importación',
        'costeo' => 'Costeo y rentabilidad',
        'portal' => 'Portal público',
        'ventas' => 'Ventas y clientes',
        'comisiones' => 'Comisiones',
        'taller' => 'Taller y órdenes de trabajo',
        'cartera' => 'Cartera de crédito propio',
        'nomina' => 'Nómina',
        'inversionistas' => 'Inversionistas',
        'reportes' => 'Reportes avanzados',
        'ia' => 'Lectura de documentos con IA',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->required()
                        ->maxLength(60)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set, $record) => $record
                            ? null
                            : $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('slug')->required()->maxLength(60)->unique(ignoreRecord: true),

                    TextInput::make('precio_mensual')->label('Precio mensual')->numeric()->required()->prefix('Q'),
                    TextInput::make('orden')->numeric()->default(0)->helperText('Para ordenar la lista.'),

                    Textarea::make('descripcion')->label('Descripción')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Límites')
                ->description('Dejalos vacíos para que no tengan tope.')
                ->columns(3)
                ->schema([
                    TextInput::make('max_sucursales')->label('Sucursales')->numeric()->placeholder('Sin límite'),
                    TextInput::make('max_usuarios')->label('Usuarios')->numeric()->placeholder('Sin límite'),
                    TextInput::make('max_unidades_activas')->label('Unidades en inventario')->numeric()->placeholder('Sin límite'),

                    TextInput::make('max_lecturas_ia')
                        ->label('Lecturas de IA al mes')
                        ->numeric()
                        ->placeholder('Sin límite')
                        ->helperText('Tope para que un cliente desbocado no te coma el crédito.'),
                ]),

            Section::make('Qué incluye')
                ->schema([
                    CheckboxList::make('modulos')
                        ->label('Módulos')
                        ->options(self::MODULOS)
                        ->columns(2)
                        ->bulkToggleable(),

                    Toggle::make('activo')->label('Se puede vender')->default(true),
                ]),
        ]);
    }
}
