<?php

namespace App\Filament\Resources\Ventas\Pages;

use App\Actions\RegistrarVenta;
use App\Filament\Resources\Ventas\VentaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    public function getTitle(): string
    {
        return "Venta {$this->record->numero}";
    }

    /**
     * Pasar una cotización a cerrada dispara todo el cierre: comisión, gasto y
     * la unidad marcada como vendida.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $veniaAbierta = ! $record->estaCerrada();

        $data['precio_final'] = bcsub((string) $data['precio_venta'], (string) ($data['descuento'] ?? 0), 2);

        $record->update($data);

        if ($veniaAbierta && $data['estado'] === 'cerrada') {
            app(RegistrarVenta::class)->cerrar($record->refresh());
        }

        return $record->refresh();
    }
}
