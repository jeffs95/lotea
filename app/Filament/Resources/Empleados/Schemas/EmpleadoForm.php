<?php

namespace App\Filament\Resources\Empleados\Schemas;

use App\Models\Empleado;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmpleadoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Quién es')
                ->columns(3)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Código')
                        ->required()
                        ->maxLength(20)
                        ->scopedUnique(ignoreRecord: true),

                    TextInput::make('nombres')->required()->maxLength(80),
                    TextInput::make('apellidos')->required()->maxLength(80),

                    TextInput::make('dpi')->label('DPI')->maxLength(20),
                    TextInput::make('nit')->label('NIT')->maxLength(20),
                    TextInput::make('igss_afiliacion')->label('Afiliación IGSS')->maxLength(20),

                    DatePicker::make('fecha_nacimiento')->label('Fecha de nacimiento')->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('email')->label('Correo')->email()->maxLength(120),

                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),

            Section::make('El puesto')
                ->columns(3)
                ->schema([
                    TextInput::make('puesto')->required()->maxLength(80)->placeholder('Mecánico, Vendedor, Cajera'),

                    Select::make('area')->label('Área')->options(Empleado::AREAS)->default('administracion')->required()->live()->native(false),

                    Select::make('sucursal_id')
                        ->label('Sucursal')
                        ->relationship('sucursal', 'nombre')
                        ->searchable()
                        ->preload()
                        ->native(false),

                    Select::make('tipo_contrato')->label('Tipo de contrato')->options(Empleado::CONTRATOS)->default('indefinido')->required()->native(false),
                    DatePicker::make('fecha_ingreso')->label('Fecha de ingreso')->required()->default(now())->native(false)->displayFormat('d/m/Y'),

                    Select::make('user_id')
                        ->label('Usuario del sistema')
                        ->relationship('usuario', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Solo si esta persona entra al sistema. El mecánico normalmente no.'),
                ]),

            Section::make('Sueldo')
                ->columns(3)
                ->schema([
                    TextInput::make('salario_base')->label('Salario base')->numeric()->required()->default(0)->prefix('Q'),

                    TextInput::make('bonificacion_incentivo')
                        ->label('Bonificación incentivo')
                        ->numeric()
                        ->default(250)
                        ->prefix('Q')
                        ->helperText('Decreto 78-89. No es afecta a IGSS.'),

                    TextInput::make('costo_hora')
                        ->label('Costo por hora')
                        ->numeric()
                        ->prefix('Q')
                        ->visible(fn (callable $get) => $get('area') === 'taller')
                        ->helperText('Con esto el taller le carga la mano de obra a cada unidad.'),

                    Toggle::make('es_mecanico')
                        ->label('Trabaja en órdenes del taller')
                        ->visible(fn (callable $get) => $get('area') === 'taller')
                        ->helperText('Aparecerá en la lista de mecánicos al asignar tareas.'),

                    TextInput::make('banco')->maxLength(60),
                    TextInput::make('cuenta_banco')->label('No. de cuenta')->maxLength(40),
                ]),

            Section::make('Estado')
                ->columns(3)
                ->schema([
                    Toggle::make('activo')->default(true),
                    DatePicker::make('fecha_baja')->label('Fecha de baja')->native(false)->displayFormat('d/m/Y')->live(),
                    TextInput::make('motivo_baja')
                        ->label('Motivo de la baja')
                        ->maxLength(120)
                        ->visible(fn (callable $get) => filled($get('fecha_baja'))),
                    Textarea::make('notas')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
