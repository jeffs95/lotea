<?php

namespace App\Filament\Resources\OrdenesTrabajo\Pages;

use App\Actions\AbrirOrdenTrabajo;
use App\Filament\Resources\OrdenesTrabajo\OrdenTrabajoResource;
use App\Models\Unidad;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrdenTrabajo extends CreateRecord
{
    protected static string $resource = OrdenTrabajoResource::class;

    protected static ?string $title = 'Abrir orden de trabajo';

    /** La acción se encarga del correlativo y de mandar la unidad al taller. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(AbrirOrdenTrabajo::class)->ejecutar(Unidad::findOrFail($data['unidad_id']), $data);
    }
}
