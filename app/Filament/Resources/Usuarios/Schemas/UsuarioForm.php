<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del usuario')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(120),
                    TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(120),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    Toggle::make('activo')
                        ->default(true)
                        ->helperText('Apagado le quita el acceso sin borrar su historial.'),
                ]),

            Section::make('Acceso')
                ->columns(2)
                ->schema([
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Dejala en blanco para no cambiarla.'
                            : null),

                    Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => Role::pluck('name', 'id'))
                        ->helperText('Definen qué puede ver y hacer dentro de esta empresa.'),
                ]),
        ]);
    }
}
