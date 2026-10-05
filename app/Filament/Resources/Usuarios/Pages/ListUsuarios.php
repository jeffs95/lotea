<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsuarios extends ListRecords
{
    /**
     * Por qué no está el botón de agregar.
     *
     * Se esconde al llegar al tope del plan, y un botón que ayer
     * estaba y hoy no deja a la gente buscándolo. Mejor decirlo aquí.
     */
    public function getSubheading(): ?string
    {
        return UsuarioResource::avisoDelTope();
    }

    protected static string $resource = UsuarioResource::class;

    protected static ?string $title = 'Usuarios';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo usuario')];
    }
}
