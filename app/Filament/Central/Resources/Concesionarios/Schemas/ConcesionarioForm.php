<?php

namespace App\Filament\Central\Resources\Concesionarios\Schemas;

use App\Models\Plan;
use App\Support\AlmacenDeArchivos;
use App\Support\MarcaDelCliente;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ConcesionarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El concesionario')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre')
                        ->label('Razón social')
                        ->required()
                        ->maxLength(160)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, callable $set, $record) => $record
                            ? null
                            : $set('slug', Str::slug($state ?? ''))),

                    TextInput::make('nombre_comercial')
                        ->label('Nombre comercial')
                        ->maxLength(160)
                        ->helperText('El que ve el público en su portal.'),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(60)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated()
                        ->helperText('Su dirección de acceso: /app/{slug}. No se cambia después.'),

                    TextInput::make('nit')->label('NIT')->maxLength(20),
                    TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('email')->label('Correo')->email()->maxLength(120),
                    Textarea::make('direccion')->label('Dirección')->rows(2)->columnSpanFull(),
                ]),

            Section::make('Usuario dueño')
                ->description('Con esta cuenta entra el cliente a su panel. Solo se pide al dar de alta.')
                ->columns(2)
                ->visibleOn('create')
                ->schema([
                    TextInput::make('dueno_nombre')->label('Nombre')->required()->maxLength(120),
                    TextInput::make('dueno_email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(120)
                        ->unique(table: 'users', column: 'email'),
                    TextInput::make('dueno_telefono')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('dueno_password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->helperText('Se la pasás al cliente y que la cambie al entrar.'),
                    TextInput::make('sucursal_principal')
                        ->label('Nombre de su primera sucursal')
                        ->default('Casa matriz')
                        ->maxLength(120),
                ]),

            Section::make('Suscripción')
                ->columns(2)
                ->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->relationship('plan', 'nombre')
                        ->options(fn () => Plan::where('activo', true)->orderBy('orden')->get()
                            ->mapWithKeys(fn (Plan $p) => [$p->id => "{$p->nombre} · Q ".number_format((float) $p->precio_mensual, 2)])
                            ->all())
                        ->required()
                        ->native(false),

                    Toggle::make('activa')
                        ->label('Cliente activo')
                        ->default(true)
                        ->helperText('Apagado es una baja. Para falta de pago usá «Suspender».'),

                    DatePicker::make('fecha_activacion')->label('Empezó el')->native(false)->displayFormat('d/m/Y')->default(now()),
                    DatePicker::make('fecha_vencimiento')->label('Contrato hasta')->native(false)->displayFormat('d/m/Y'),
                ]),

            Section::make('Su portal')
                ->columns(2)
                ->schema([
                    TextInput::make('dominio')
                        ->label('Dominio propio')
                        ->maxLength(120)
                        ->unique(ignoreRecord: true)
                        ->placeholder('autosdelvalle.com')
                        ->helperText('Mientras no lo tenga, su portal vive en /v/{slug}.'),

                    ColorPicker::make('color_primario')
                        ->label('Color de marca')
                        ->default(MarcaDelCliente::COLOR_POR_DEFECTO)
                        ->hex()
                        // Con un valor que no sea hex, la paleta del panel no
                        // se puede generar y el cliente se queda sin panel.
                        ->rule('regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/')
                        ->helperText('Pinta su panel y su portal.'),
                ]),

            Section::make('Su marca')
                ->description('Lo que el cliente ve en su panel y en su portal. Si no sube nada, usa las iniciales sobre el color de marca.')
                ->columns(3)
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logo')
                        ->image()
                        ->disk(AlmacenDeArchivos::nombreDelDisco())
                        ->directory('marcas')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('PNG con fondo transparente. Se ve a 32 px de alto.'),

                    FileUpload::make('logo_oscuro_path')
                        ->label('Logo para fondo oscuro')
                        ->image()
                        ->disk(AlmacenDeArchivos::nombreDelDisco())
                        ->directory('marcas')
                        ->visibility('public')
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('Opcional. Si no lo sube, en modo oscuro se usa el normal.'),

                    FileUpload::make('favicon_path')
                        ->label('Favicon')
                        ->image()
                        ->disk(AlmacenDeArchivos::nombreDelDisco())
                        ->directory('marcas')
                        ->visibility('public')
                        ->maxSize(512)
                        ->helperText('Cuadrado, 64x64 o más. Es lo que ve en la pestaña.'),
                ]),

            Section::make('Notas internas')
                ->description('Solo lo ve Lotea. El cliente nunca ve esto.')
                ->collapsed()
                ->schema([
                    TextInput::make('contacto_nombre')->label('Con quién hablamos')->maxLength(120),
                    TextInput::make('contacto_telefono')->label('Su teléfono directo')->tel()->maxLength(30),
                    Textarea::make('notas_internas')->label('Notas')->rows(3),
                ]),
        ]);
    }
}
