<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Actions\CambiarEstadoUnidad;
use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Mover la unidad de etapa desde el panel.
 *
 * Solo ofrece los destinos que la máquina de estados permite, así que el
 * usuario no puede inventar un salto imposible desde la interfaz.
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
            ->visible(fn (Unidad $record) => filled($record->estado->siguientes()))
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

                Select::make('estado')
                    ->label('Pasa a')
                    ->options(fn (Unidad $record) => collect($record->estado->siguientes())
                        ->mapWithKeys(fn (EstadoUnidad $e) => [$e->value => $e->getLabel()])
                        ->all())
                    ->required()
                    ->native(false),

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
