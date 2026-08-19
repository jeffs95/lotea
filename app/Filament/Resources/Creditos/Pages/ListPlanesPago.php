<?php

namespace App\Filament\Resources\Creditos\Pages;

use App\Filament\Resources\Creditos\PlanPagoResource;
use Filament\Resources\Pages\ListRecords;

class ListPlanesPago extends ListRecords
{
    protected static string $resource = PlanPagoResource::class;

    protected static ?string $title = 'Cartera';
}
