<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Enums\EstadoUnidad;
use App\Filament\Resources\Unidades\Actions\LeerDocumentoAction;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\UnidadTransicion;
use Filament\Resources\Pages\CreateRecord;

class CreateUnidad extends CreateRecord
{
    protected static string $resource = UnidadResource::class;

    protected function getHeaderActions(): array
    {
        return [LeerDocumentoAction::make()];
    }

    protected function afterCreate(): void
    {
        // La unidad nace con su primera línea de historial: sin esto, el aging
        // de la primera etapa no tendría desde cuándo contar.
        $this->record->update([
            'estado_desde' => now(),
            ...$this->fechasQueElEstadoImplica(),
        ]);

        UnidadTransicion::create([
            'unidad_id' => $this->record->id,
            'user_id' => auth()->id(),
            'estado_anterior' => null,
            'estado_nuevo' => $this->record->estado,
            'ocurrio_en' => now(),
            'nota' => $this->record->estado === EstadoUnidad::Comprada
                ? 'Unidad registrada'
                : 'Unidad registrada directamente en «'.$this->record->estado->getLabel().'»',
        ]);
    }

    /**
     * Sella las fechas hito que el estado inicial da por hechas.
     *
     * Si alguien registra un carro que ya está listo para la venta, no tiene
     * sentido que sus días en el patio arranquen en cero: ya llevaba tiempo
     * ahí antes de que existiera la ficha.
     */
    protected function fechasQueElEstadoImplica(): array
    {
        $unidad = $this->record;
        $etapa = $unidad->estado->etapa();
        $fechas = [];

        if (in_array($etapa, ['preparacion', 'venta'], true) && blank($unidad->fecha_recepcion)) {
            $fechas['fecha_recepcion'] = ($unidad->fecha_compra ?? now())->toDateString();
        }

        if ($etapa === 'venta' && blank($unidad->fecha_lista)) {
            $fechas['fecha_lista'] = ($unidad->fecha_recepcion ?? $unidad->fecha_compra ?? now())->toDateString();
        }

        return $fechas;
    }
}
