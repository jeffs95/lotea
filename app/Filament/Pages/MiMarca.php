<?php

namespace App\Filament\Pages;

use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\MarcaDelCliente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Para que el concesionario se ponga su propio logo y su color.
 *
 * Lo mismo que el operador de Lotea puede hacer desde el central, pero en
 * manos del dueño: es su marca y normalmente el logo lo tiene él, no nosotros.
 * Va con permiso propio para que pueda dárselo a quien quiera —o a nadie— sin
 * que un vendedor le cambie los colores de la empresa.
 */
class MiMarca extends Page implements HasForms
{
    use InteractsWithForms;

    public const PERMISO = 'administrar_marca';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Herramientas';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'mi-marca';

    protected static ?string $navigationLabel = 'Mi marca';

    protected static ?string $title = 'Mi marca';

    protected string $view = 'filament.pages.mi-marca';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(self::PERMISO) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->empresa()->only([
            'logo_path', 'logo_oscuro_path', 'favicon_path', 'color_primario',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Su logo')
                    ->description('Se ve arriba en este panel y en la página donde exhibe sus vehículos.')
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
                            ->label('Icono de la pestaña')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->maxSize(512)
                            ->helperText('Cuadrado, de 64x64 o más.'),
                    ]),

                Section::make('Su color')
                    ->description('Con esto se pintan los botones y los enlaces de todo el sistema.')
                    ->schema([
                        ColorPicker::make('color_primario')
                            ->label('Color de marca')
                            ->hex()
                            // Sin un hex válido no se puede generar la paleta del
                            // panel, y el cliente se quedaría sin panel.
                            ->rule('regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/')
                            ->default(Empresa::COLOR_POR_DEFECTO)
                            ->required(),
                    ]),
            ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardar')
                ->label('Guardar cambios')
                ->icon(Heroicon::OutlinedCheck)
                ->action('guardar'),
        ];
    }

    public function guardar(): void
    {
        $this->empresa()->update($this->form->getState());

        // La marca vive memorizada por request; sin esto el panel se repintaría
        // con el color anterior.
        MarcaDelCliente::olvidar();

        Notification::make()
            ->title('Su marca quedó guardada')
            ->body('Así se va a ver de ahora en adelante, aquí y en su página pública.')
            ->success()
            ->send();

        // Un redirect y no un refresh de Livewire: el color y el logo se
        // emiten en el <head>, que solo se vuelve a armar con una carga nueva.
        $this->redirect(static::getUrl());
    }

    /**
     * El concesionario de quien está viendo la pantalla.
     *
     * Sale del tenant del panel, nunca de un id que venga del formulario: así
     * no hay forma de escribirle la marca a otro cliente.
     */
    protected function empresa(): Empresa
    {
        return Filament::getTenant();
    }
}
