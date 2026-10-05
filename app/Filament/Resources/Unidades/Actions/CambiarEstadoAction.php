<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Actions\CambiarEstadoUnidad;
use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Mover la unidad de etapa desde el panel.
 *
 * Muestra el camino completo con lo andado marcado, y deja tocar cualquier
 * etapa. La máquina de estados sigue valiendo para lo que dispara el programa
 * solo —una venta exige que el carro esté donde debe—, pero a la persona que
 * tiene el carro delante no se le discute en qué etapa está.
 */
class CambiarEstadoAction
{
    /**
     * En qué etapa está el carro y desde cuándo.
     *
     * Los días cuentan tanto como la etapa: «En aduana» no dice nada, «En
     * aduana desde hace 23 días» dice que hay que llamar al agente.
     */
    public static function dondeEsta(Unidad $unidad): string
    {
        $texto = '<span class="font-semibold">'.e($unidad->estado->getLabel()).'</span>';

        $dias = $unidad->dias_en_estado;

        if ($dias === null) {
            return $texto;
        }

        $cuando = match (true) {
            $dias === 0 => 'desde hoy',
            $dias === 1 => 'desde hace 1 día',
            default => "desde hace {$dias} días",
        };

        return $texto.'<span class="text-gray-500"> · '.$cuando.'</span>';
    }

    public static function make(string $nombre = 'cambiarEstado'): Action
    {
        return Action::make($nombre)
            ->label('Cambiar estado')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (Unidad $record) => $record->estado !== EstadoUnidad::Baja)
            ->modalHeading(fn (Unidad $record) => "Cambiar estado · {$record->stock_no}")
            ->schema([
                /*
                 * De dónde viene, antes de preguntar a dónde va.
                 *
                 * El modal solo mostraba el selector vacío, así que quien lo
                 * abría no sabía en qué etapa estaba el carro y tenía que
                 * cerrarlo para ir a mirarlo. Los días cuentan tanto como la
                 * etapa: «En aduana» no dice nada, «En aduana desde hace 23
                 * días» dice que hay que llamar al agente.
                 */
                Placeholder::make('estado_actual')
                    ->label('Hoy está en')
                    ->content(fn (Unidad $record) => new HtmlString(self::dondeEsta($record))),

                /*
                 * El camino entero, no solo los dos destinos de al lado.
                 *
                 * La lista desplegable escondía dos cosas: en qué punto del
                 * recorrido va el carro, y que existen etapas más allá de las
                 * que la máquina de estados ofrecía. Y obligaba a un patio que
                 * recién arranca a inventarle diez etapas de importación a un
                 * carro que ya tiene parqueado y listo.
                 */
                ViewField::make('estado')
                    ->label('Pasa a')
                    ->view('filament.resources.unidades.linea-de-tiempo-estado')
                    ->required(),

                Textarea::make('nota')
                    ->label('Nota')
                    ->rows(2)
                    ->placeholder('Qué pasó. Queda en el historial de la unidad.'),
            ])
            ->action(function (Unidad $record, array $data) {
                try {
                    app(CambiarEstadoUnidad::class)->ejecutar(
                        $record,
                        EstadoUnidad::from($data['estado']),
                        $data['nota'] ?? null,
                        // Lo pidió una persona mirando la línea de tiempo, con
                        // el camino entero a la vista: no hay salto que
                        // explicarle. El historial guarda de dónde venía.
                        forzado: true,
                    );

                    Notification::make()
                        ->title('Estado actualizado')
                        ->body("{$record->stock_no} pasó a «".EstadoUnidad::from($data['estado'])->getLabel().'».')
                        ->success()
                        ->send();
                } catch (DomainException $e) {
                    Notification::make()
                        ->title('No se pudo cambiar el estado')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
