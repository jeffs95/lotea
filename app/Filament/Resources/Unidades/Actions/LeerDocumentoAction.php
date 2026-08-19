<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Actions\RegistrarLecturaIa;
use App\Actions\ResolverCatalogoVehiculo;
use App\Enums\TipoPlaca;
use App\Services\ConversorDeDocumentos;
use App\Services\LectorDeDocumentos;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\BasePage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Llena la ficha leyendo el documento del carro.
 *
 * Lo que devuelve es una propuesta, no un guardado: los campos quedan
 * rellenados en pantalla y la persona revisa antes de crear la unidad. La IA
 * se equivoca, y un VIN mal tecleado sigue al carro toda su vida.
 */
class LeerDocumentoAction
{
    public static function make(): Action
    {
        return Action::make('leerDocumento')
            ->label('Llenar con IA')
            ->icon('heroicon-o-sparkles')
            ->color('info')
            ->modalHeading('Leer los documentos del vehículo')
            ->modalDescription('Tarjeta de circulación, título americano, hoja del lote de subasta. Podés subir varios: se leen juntos y se combinan, porque cada uno trae datos distintos del mismo carro.')
            ->modalSubmitActionLabel('Leer documentos')
            // El botón aparece si el cliente contrató el módulo, no si hay
            // llave configurada: que falte la llave es problema del proveedor,
            // y el cliente que paga tiene que ver lo que paga.
            ->visible(fn () => Filament::getTenant()?->tieneModulo('ia') ?? false)
            ->schema([
                FileUpload::make('documento')
                    ->label('Fotos o PDF de los documentos')
                    ->multiple()
                    ->maxFiles(ConversorDeDocumentos::IMAGENES_MAXIMAS)
                    ->reorderable()
                    ->panelLayout('grid')
                    ->disk('local')
                    ->directory('lecturas')
                    ->visibility('private')
                    ->acceptedFileTypes(self::tiposAceptados())
                    ->maxSize(12 * 1024)
                    ->required()
                    ->helperText(self::ayuda()),
            ])
            // $livewire y no el utilitario `set`: esta acción vive en el
            // encabezado de la página, donde no hay componente de formulario
            // desde el cual construirlo. BasePage y no Resources\Pages\Page,
            // para que sirva igual en la pantalla de levantamiento.
            ->action(function (array $data, BasePage $livewire) {
                $relativas = self::rutasDeArchivos($data['documento'] ?? null);

                if ($relativas === []) {
                    Notification::make()->title('No se recibió ningún archivo')->danger()->send();

                    return;
                }

                if ($problema = self::motivoParaNoLeer()) {
                    Notification::make()->title($problema)->warning()->persistent()->send();
                    Storage::disk('local')->delete($relativas);

                    return;
                }

                try {
                    $resultado = app(LectorDeDocumentos::class)->leer(
                        array_map(fn (string $ruta) => Storage::disk('local')->path($ruta), $relativas),
                    );

                    app(RegistrarLecturaIa::class)->exitosa($resultado['consumo'], count($resultado['datos']));

                    self::volcarEnElFormulario($resultado['datos'], $livewire);
                    self::avisar($resultado);
                } catch (RuntimeException $e) {
                    app(RegistrarLecturaIa::class)->fallida($e->getMessage(), count($relativas));

                    Notification::make()->title('No se pudo leer')->body($e->getMessage())->danger()->send();
                } catch (Throwable $e) {
                    report($e);

                    app(RegistrarLecturaIa::class)->fallida($e->getMessage(), count($relativas));

                    Notification::make()
                        ->title('Algo falló al leer el documento')
                        ->body('Quedó registrado para revisarlo. Podés llenar la ficha a mano.')
                        ->danger()
                        ->send();
                } finally {
                    // Los documentos del cliente no se quedan en el servidor.
                    Storage::disk('local')->delete($relativas);
                }
            });
    }

    /**
     * Por qué no se puede leer ahora mismo, si es que hay un motivo.
     *
     * Se distingue el cupo agotado —que es del cliente y se resuelve con un
     * upgrade— de la llave sin configurar, que es del proveedor y no es culpa
     * de quien está tratando de trabajar.
     */
    protected static function motivoParaNoLeer(): ?string
    {
        $empresa = Filament::getTenant();

        if ($empresa && ! $empresa->puedeLeerConIa()) {
            $tope = $empresa->plan?->max_lecturas_ia;

            return $tope
                ? "Ya usaste las {$tope} lecturas de este mes. El mes entrante se reinicia, o podés subir de plan."
                : 'La lectura con IA no está habilitada en tu plan.';
        }

        if (! app(LectorDeDocumentos::class)->estaDisponible()) {
            return 'El servicio de lectura no está configurado. Avisale a soporte.';
        }

        return null;
    }

    /**
     * FileUpload guarda su estado como array (uuid => ruta), aun con un solo
     * archivo.
     *
     * @param  array<string, string>|string|null  $valor
     * @return array<int, string>
     */
    protected static function rutasDeArchivos(array|string|null $valor): array
    {
        return collect(is_array($valor) ? $valor : [$valor])
            ->filter(fn ($ruta) => is_string($ruta) && $ruta !== '')
            ->values()
            ->all();
    }

    /**
     * Escribe lo leído sobre el formulario que ya está en pantalla, sin pisar
     * lo que la persona haya escrito antes.
     *
     * @param  array<string, mixed>  $datos
     */
    protected static function volcarEnElFormulario(array $datos, BasePage $livewire): void
    {
        $nuevos = app(ResolverCatalogoVehiculo::class)->ejecutar(
            $datos['marca'] ?? null,
            $datos['linea'] ?? null,
        );

        $nuevos = array_filter($nuevos, fn ($valor) => $valor !== null);

        foreach ($datos as $campo => $valor) {
            if (! in_array($campo, ['marca', 'linea'], true)) {
                $nuevos[$campo] = $valor;
            }
        }

        // El tipo sale de la letra de la placa, no de lo que diga el modelo.
        if (filled($nuevos['placa'] ?? null)) {
            $nuevos['tipo_placa'] = TipoPlaca::desdeLaPlaca($nuevos['placa'])?->value;
        }

        $livewire->form->fill([...$livewire->data, ...$nuevos]);
    }

    /** @param array{datos: array, documentos: array<int, string>, aviso: ?string} $resultado */
    protected static function avisar(array $resultado): void
    {
        $cuantos = count($resultado['datos']);

        if ($cuantos === 0) {
            Notification::make()
                ->title('No se encontraron datos')
                ->body('Probá con fotos más nítidas, de frente y con buena luz.')
                ->warning()
                ->send();

            return;
        }

        $cuerpo = collect([
            self::describirDocumentos($resultado['documentos']),
            'Revisá los datos antes de guardar, sobre todo el VIN.',
            $resultado['aviso'],
        ])->filter()->implode(' ');

        Notification::make()
            ->title("Se llenaron {$cuantos} ".($cuantos === 1 ? 'campo' : 'campos'))
            ->body($cuerpo)
            // Si hubo contradicciones entre documentos, que no pase inadvertido.
            ->color(filled($resultado['aviso']) ? 'warning' : 'success')
            ->persistent()
            ->send();
    }

    /** @param array<int, string> $documentos */
    protected static function describirDocumentos(array $documentos): ?string
    {
        $nombres = collect($documentos)
            ->map(fn (string $tipo) => match ($tipo) {
                'tarjeta_circulacion' => 'la tarjeta de circulación',
                'titulo_usa' => 'el título americano',
                'hoja_subasta' => 'la hoja de subasta',
                default => null,
            })
            ->filter()
            ->values();

        if ($nombres->isEmpty()) {
            return null;
        }

        return 'Leído de '.$nombres->join(', ', ' y ').'.';
    }

    /** @return array<int, string> */
    protected static function tiposAceptados(): array
    {
        $tipos = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

        if (app(ConversorDeDocumentos::class)->puedeLeerPdf()) {
            $tipos[] = 'application/pdf';
        }

        return $tipos;
    }

    protected static function ayuda(): string
    {
        $formatos = app(ConversorDeDocumentos::class)->puedeLeerPdf()
            ? 'Fotos (JPG, PNG) o PDF'
            : 'Fotos (JPG, PNG)';

        return $formatos.', hasta '.ConversorDeDocumentos::IMAGENES_MAXIMAS
            .' documentos de 12 MB cada uno. Se borran del servidor apenas se leen.';
    }
}
