<?php

namespace App\Filament\Resources\CategoriasCosto\Pages;

use App\Filament\Resources\CategoriasCosto\CategoriaCostoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaCosto extends EditRecord
{
    protected static string $resource = CategoriaCostoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn () => ! $this->getRecord()->es_sistema)];
    }
}
