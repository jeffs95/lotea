<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\Actions\CambiarEstadoAction;
use App\Filament\Resources\Unidades\UnidadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUnidad extends EditRecord
{
    protected static string $resource = UnidadResource::class;

    public function getTitle(): string
    {
        return "{$this->record->stock_no} · {$this->record->descripcion}";
    }

    protected function getHeaderActions(): array
    {
        return [
            CambiarEstadoAction::make(),
            DeleteAction::make(),
        ];
    }
}
