<?php

namespace App\Filament\Pages;

use App\Actions\GenerarVariantesDeMarca;
use App\Models\Empresa;
use App\Support\AlmacenDeArchivos;
use App\Support\MarcaDelCliente;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
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

    protected static ?string $navigationLabel = 'Mi marca y contacto';

    protected static ?string $title = 'Mi marca y contacto';

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
            'logo_path', 'logo_claro_path', 'logo_oscuro_path',
            'isotipo_path', 'favicon_path', 'portada_path',
            'color_primario',
            'telefono', 'whatsapp', 'email',
            'facebook', 'instagram', 'tiktok', 'youtube',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Su logo original')
                    ->description('El archivo tal como se lo dio su diseñador. De aquí salen las demás versiones.')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->maxSize(6144)
                            ->helperText('Este no se publica directamente: es el original del que se derivan los de abajo.'),
                    ]),

                Section::make('Dónde va cada versión')
                    ->description('Cada casilla dice exactamente en qué parte del sistema aparece. Lo que deje vacío se llena solo a partir del logo original.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('logo_claro_path')
                            ->label('Sobre fondo blanco')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->maxSize(6144)
                            ->helperText(new HtmlString(
                                '<strong>Se ve en:</strong> la barra de arriba y el pie de su página pública, '
                                .'las etiquetas del parabrisas y este panel de día.<br>'
                                .'Necesita trazo <strong>oscuro</strong> y fondo transparente.'
                            )),

                        FileUpload::make('logo_oscuro_path')
                            ->label('Sobre fondo oscuro')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1200')
                            ->maxSize(6144)
                            ->helperText(new HtmlString(
                                '<strong>Se ve en:</strong> la portada de su página, la sección «Encontranos» '
                                .'y este panel en modo noche.<br>'
                                .'Necesita trazo <strong>claro</strong> y fondo transparente.'
                            )),

                        FileUpload::make('isotipo_path')
                            ->label('Solo el símbolo, sin el nombre')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('600')
                            ->maxSize(6144)
                            ->helperText(new HtmlString(
                                '<strong>Se ve en:</strong> el centro del código QR que se pega en el parabrisas.<br>'
                                .'Ahí el nombre no se alcanza a leer. Trazo <strong>oscuro</strong>, va sobre blanco.'
                            )),

                        FileUpload::make('favicon_path')
                            ->label('Icono para navegadores viejos')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('256')
                            ->maxSize(6144)
                            ->helperText(new HtmlString(
                                '<strong>Se ve en:</strong> la pestaña del navegador, solo en los que no '
                                .'entienden iconos modernos.<br>'
                                .'Los demás usan sus iniciales sobre su color, que se dibujan solas.'
                            )),
                    ]),

                Section::make('La portada de su página')
                    ->description('La imagen grande de arriba, detrás del titular.')
                    ->schema([
                        FileUpload::make('portada_path')
                            ->label('Imagen de portada')
                            ->image()
                            ->disk(AlmacenDeArchivos::nombreDelDisco())
                            ->directory('marcas')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9', '21:9'])
                            // Se encoge en el navegador antes de subirla: una
                            // foto de celular pesa más que el límite del
                            // servidor, y al pasarse PHP descarta la petición
                            // entera sin dejar ni un error que mostrar.
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('1920')
                            ->maxSize(6144)
                            ->helperText(new HtmlString(
                                '<strong>Se ve en:</strong> el fondo de la portada y de «Encontranos».<br>'
                                .'Una foto de su patio o de un carro, apaisada y de 1600 px de ancho o más. '
                                .'Se le pone una capa oscura encima para que el texto siga leyéndose; '
                                .'si no sube ninguna, queda el degradado con su color.'
                            )),
                    ]),

                Section::make('Cómo lo contactan')
                    ->description('Sale en el portal y en los botones de contacto de cada vehículo.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('telefono')->label('Teléfono')->tel()->maxLength(30),

                        TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->maxLength(30)
                            ->helperText('Si lo deja vacío se usa el teléfono.'),

                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(120),
                    ]),

                Section::make('Redes sociales')
                    ->description('Puede pegar el enlace completo o solo el usuario. Las que deje vacías no aparecen.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('facebook')
                            ->label('Facebook')
                            ->maxLength(200)
                            ->placeholder('suconcesionario'),

                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->maxLength(200)
                            ->placeholder('@suconcesionario'),

                        TextInput::make('tiktok')
                            ->label('TikTok')
                            ->maxLength(200)
                            ->placeholder('@suconcesionario'),

                        TextInput::make('youtube')
                            ->label('YouTube')
                            ->maxLength(200)
                            ->placeholder('@suconcesionario'),
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
        $empresa = $this->empresa();
        $logoAnterior = $empresa->logo_path;

        $empresa->update($this->form->getState());

        // Si subió un logo nuevo, de ahí salen las versiones que dejó vacías:
        // es lo que promete el texto de ayuda de cada casilla.
        if ($empresa->logo_path !== $logoAnterior) {
            app(GenerarVariantesDeMarca::class)->ejecutar($empresa);
        }

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
