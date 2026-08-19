<?php

namespace App\Filament\Resources\GastosCompartidos\Pages;

use App\Actions\RegistrarGastoCompartido;
use App\Filament\Resources\GastosCompartidos\GastoCompartidoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateGastoCompartido extends CreateRecord
{
    protected static string $resource = GastoCompartidoResource::class;

    protected static ?string $title = 'Registrar gasto compartido';

    protected function handleRecordCreation(array $data): Model
    {
        $unidades = $data['unidades'] ?? [];
        unset($data['unidades']);

        $gasto = app(RegistrarGastoCompartido::class)->ejecutar($data, $unidades);

        Notification::make()
            ->title('Gasto repartido')
            ->body('Se repartió entre '.count($unidades).' unidades y se recalculó el costo de cada una.')
            ->success()
            ->send();

        return $gasto;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
