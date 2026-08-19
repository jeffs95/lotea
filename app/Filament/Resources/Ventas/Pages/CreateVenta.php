<?php

namespace App\Filament\Resources\Ventas\Pages;

use App\Actions\RegistrarVenta;
use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Unidad;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    protected static ?string $title = 'Nueva venta';

    /**
     * La venta no se guarda con un create pelado: la acción se encarga de la
     * comisión, del gasto y del cambio de estado de la unidad.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $unidad = Unidad::findOrFail($data['unidad_id']);

        try {
            return app(RegistrarVenta::class)->ejecutar($unidad, $data);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.unidad_id' => $e->getMessage()]);
        }
    }
}
