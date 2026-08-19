<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Actions\ResolverCatalogoVehiculo;
use App\Services\ConversorDeDocumentos;
use App\Services\LectorDeDocumentos;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
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
            ->modalHeading('Leer el documento del vehículo')
            ->modalDescription('Tarjeta de circulación, título americano o la hoja del lote de subasta. Los datos se llenan solos y vos los revisás antes de guardar.')
            ->modalSubmitActionLabel('Leer documento')
            ->visible(fn () => app(LectorDeDocumentos::class)->estaDisponible())
            ->schema([
                FileUpload::make('documento')
                    ->label('Foto o PDF del documento')
                    ->disk('local')
                    ->directory('lecturas')
                    ->visibility('private')
                    ->acceptedFileTypes(self::tiposAceptados())
                    ->maxSize(12 * 1024)
                    ->required()
                    ->helperText(self::ayuda()),
            ])
            ->action(function (array $data, Set $set) {
                $relativa = $data['documento'];

                try {
                    $resultado = app(LectorDeDocumentos::class)->leer(Storage::disk('local')->path($relativa));

                    self::volcarEnElFormulario($resultado['datos'], $set);
                    self::avisar($resultado);
                } catch (RuntimeException $e) {
                    Notification::make()->title('No se pudo leer')->body($e->getMessage())->danger()->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Algo falló al leer el documento')
                        ->body('Quedó registrado para revisarlo. Podés llenar la ficha a mano.')
                        ->danger()
                        ->send();
                } finally {
                    // El documento del cliente no se queda en el servidor.
                    Storage::disk('local')->delete($relativa);
                }
            });
    }

    /** @param array<string, mixed> $datos */
    protected static function volcarEnElFormulario(array $datos, Set $set): void
    {
        $catalogo = app(ResolverCatalogoVehiculo::class)->ejecutar(
            $datos['marca'] ?? null,
            $datos['linea'] ?? null,
        );

        foreach ($catalogo as $campo => $valor) {
            if ($valor !== null) {
                $set($campo, $valor);
            }
        }

        // La placa no es campo de la unidad: se guarda como nota para no perderla.
        if (filled($datos['placa'] ?? null)) {
            $set('notas', 'Placa según documento: '.$datos['placa']);
        }

        foreach ($datos as $campo => $valor) {
            if (in_array($campo, ['marca', 'linea', 'placa'], true)) {
                continue;
            }

            $set($campo, $valor);
        }
    }

    /** @param array{datos: array, tipo_documento: ?string, aviso: ?string} $resultado */
    protected static function avisar(array $resultado): void
    {
        $cuantos = count($resultado['datos']);

        if ($cuantos === 0) {
            Notification::make()
                ->title('No se encontraron datos')
                ->body('Probá con una foto más nítida, de frente y con buena luz.')
                ->warning()
                ->send();

            return;
        }

        $documento = match ($resultado['tipo_documento']) {
            'tarjeta_circulacion' => 'tarjeta de circulación',
            'titulo_usa' => 'título americano',
            'hoja_subasta' => 'hoja de subasta',
            default => 'documento',
        };

        Notification::make()
            ->title("Se llenaron {$cuantos} campos")
            ->body(trim("Leído de la {$documento}. Revisá los datos antes de guardar, sobre todo el VIN. ".($resultado['aviso'] ?? '')))
            ->success()
            ->persistent()
            ->send();
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
        return app(ConversorDeDocumentos::class)->puedeLeerPdf()
            ? 'Foto (JPG, PNG) o PDF, hasta 12 MB. Se borra del servidor apenas se lee.'
            : 'Foto (JPG, PNG), hasta 12 MB. Se borra del servidor apenas se lee.';
    }
}
