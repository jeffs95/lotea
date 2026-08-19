<?php

namespace App\Filament\Resources\Cajas\Pages;

use App\Filament\Resources\Cajas\CajaResource;
use App\Filament\Resources\Cajas\Actions\AccionesDeCaja;
use Filament\Resources\Pages\EditRecord;

class EditCaja extends EditRecord
{
    protected static string $resource = CajaResource::class;

    public function getTitle(): string
    {
        $moneda = $this->record->esEnDolares() ? '$' : 'Q';

        return "{$this->record->nombre} · {$moneda} ".number_format((float) $this->record->saldo, 2);
    }

    protected function getHeaderActions(): array
    {
        return [
            AccionesDeCaja::registrarMovimiento(),
            AccionesDeCaja::trasladar(),
            AccionesDeCaja::arquear(),
        ];
    }
}
