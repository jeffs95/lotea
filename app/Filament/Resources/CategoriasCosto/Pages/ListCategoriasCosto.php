<?php

namespace App\Filament\Resources\CategoriasCosto\Pages;

use App\Filament\Resources\CategoriasCosto\CategoriaCostoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoriasCosto extends ListRecords
{
    protected static string $resource = CategoriaCostoResource::class;

    protected static ?string $title = 'Categorías de costo';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva categoría')];
    }
}
