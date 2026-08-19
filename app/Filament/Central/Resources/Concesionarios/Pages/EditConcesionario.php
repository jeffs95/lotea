<?php

namespace App\Filament\Central\Resources\Concesionarios\Pages;

use App\Filament\Central\Resources\Concesionarios\ConcesionarioResource;
use Filament\Resources\Pages\EditRecord;

class EditConcesionario extends EditRecord
{
    protected static string $resource = ConcesionarioResource::class;

    public function getTitle(): string
    {
        return $this->record->nombre_comercial ?: $this->record->nombre;
    }
}
