<?php

namespace App\Filament\Resources\Unidades\Actions;

use App\Actions\CambiarEstadoUnidad;
use App\Enums\EstadoUnidad;
use App\Models\Unidad;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Mover la unidad de etapa desde el panel.
 *
 * Solo ofrece los destinos que la máquina de estados permite, así que el
 * usuario no puede inventar un salto imposible desde la interfaz.
 */
class CambiarEstadoAction
{
    public static function make(string $nombre = 'cambiarEstado'): Action
    {
        return Action::make($nombre)
            ->label('Cambiar estado')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->visible(fn (Unidad $record) => filled($record->estado->siguientes()))
            ->schema([
                Select::make('estado')
                    ->label('Nuevo estado')
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
