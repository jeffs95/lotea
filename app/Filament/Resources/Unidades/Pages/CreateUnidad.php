<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\UnidadTransicion;
use Filament\Resources\Pages\CreateRecord;

class CreateUnidad extends CreateRecord
{
    protected static string $resource = UnidadResource::class;

    protected function afterCreate(): void
    {
        // La unidad nace con su primera línea de historial: sin esto, el aging
        // de la primera etapa no tendría desde cuándo contar.
        $this->record->update(['estado_desde' => now()]);

        UnidadTransicion::create([
            'unidad_id' => $this->record->id,
            'user_id' => auth()->id(),
            'estado_anterior' => null,
            'estado_nuevo' => $this->record->estado,
            'ocurrio_en' => now(),
            'nota' => 'Unidad registrada',
        ]);
    }
}
