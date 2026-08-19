<?php

namespace App\Filament\Resources\Creditos\Pages;

use App\Filament\Resources\Creditos\PlanPagoResource;
use Filament\Resources\Pages\EditRecord;

class EditPlanPago extends EditRecord
{
    protected static string $resource = PlanPagoResource::class;

    public function getTitle(): string
    {
        return "{$this->record->numero} · {$this->record->cliente->nombre}";
    }
}
