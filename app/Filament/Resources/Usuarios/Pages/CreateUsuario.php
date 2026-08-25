<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\Pages\Concerns\SincronizaRolesDeLaEmpresa;
use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUsuario extends CreateRecord
{
    use SincronizaRolesDeLaEmpresa;

    protected static string $resource = UsuarioResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->apartarRoles($data);
    }

    protected function afterCreate(): void
    {
        $this->guardarRoles();
    }
}
