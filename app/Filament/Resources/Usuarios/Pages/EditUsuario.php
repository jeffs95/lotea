<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\Pages\Concerns\SincronizaRolesDeLaEmpresa;
use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Resources\Pages\EditRecord;

class EditUsuario extends EditRecord
{
    use SincronizaRolesDeLaEmpresa;

    protected static string $resource = UsuarioResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->cargarRoles($data);
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->apartarRoles($data);
    }

    protected function afterSave(): void
    {
        $this->guardarRoles();
    }
}
