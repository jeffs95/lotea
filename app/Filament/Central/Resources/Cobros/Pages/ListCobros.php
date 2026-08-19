<?php

namespace App\Filament\Central\Resources\Cobros\Pages;

use App\Filament\Central\Resources\Cobros\CobroResource;
use App\Actions\GenerarCobrosDelMes;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCobros extends ListRecords
{
    protected static string $resource = CobroResource::class;

    protected static ?string $title = 'Cobros';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generarMes')
                ->label('Emitir mensualidades')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->modalDescription('Emite el cobro del mes a todos los clientes activos. Correrla dos veces no duplica nada.')
                ->action(function () {
                    $cobros = app(GenerarCobrosDelMes::class)->ejecutar();

                    Notification::make()
                        ->title('Mensualidades al día')
                        ->body($cobros->count().' cobros del periodo '.now()->format('Y-m').'.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()->label('Nuevo cobro'),
        ];
    }
}
