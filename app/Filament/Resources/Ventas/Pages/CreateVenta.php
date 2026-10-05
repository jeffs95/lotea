<?php

namespace App\Filament\Resources\Ventas\Pages;

use App\Actions\RegistrarVenta;
use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Cliente;
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
            $venta = app(RegistrarVenta::class)->ejecutar($unidad, $data);

            $this->avisarSiFaltaLaIdentificacion($venta->cliente);

            return $venta;
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.unidad_id' => $e->getMessage()]);
        }
    }

    /**
     * Si el comprador quedó sin documento respaldado, se dice ahora.
     *
     * No se bloquea la venta: el comprador puede no traer el DPI encima y
     * trabar el cierre por eso sería peor que el riesgo que se evita. Pero
     * tampoco se deja pasar en silencio, porque el día que haga falta va a
     * hacer falta de verdad: el traspaso queda a nombre de quien compró, y si
     * años después aparece una multa o un embargo, el número escrito a mano no
     * prueba quién fue.
     */
    protected function avisarSiFaltaLaIdentificacion(?Cliente $cliente): void
    {
        if (! $cliente || $cliente->tieneIdentificacion()) {
            return;
        }

        Notification::make()
            ->title('Falta la foto del DPI de '.$cliente->nombre)
            ->body('La venta quedó registrada. Subí la foto en la ficha del cliente cuando la tengas: '
                .'es lo que respalda a quién se le vendió si después hay un problema con el vehículo.')
            ->warning()
            ->persistent()
            ->send();
    }
}
