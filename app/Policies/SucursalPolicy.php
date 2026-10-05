<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sucursal;
use App\Support\AlcanceDelPlan;
use App\Support\Tenancy;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SucursalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Sucursal');
    }

    public function view(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('View:Sucursal');
    }

    /**
     * El permiso dice si esta persona puede dar de alta; el tope del plan,
     * si a la empresa le queda lugar.
     *
     * Va en la política y no solo en el recurso porque por aquí pasa todo:
     * el botón de la pantalla, la URL tecleada a mano y cualquier acción que
     * se agregue mañana. Puesto solo en el recurso hay que acordarse en cada
     * sitio nuevo, y olvidarlo deja un botón que lleva a un formulario que no
     * va a guardar.
     */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sucursal')
            && AlcanceDelPlan::puedeAgregar(Tenancy::empresa(), 'sucursales');
    }

    public function update(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('Update:Sucursal');
    }

    public function delete(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('Delete:Sucursal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Sucursal');
    }

    public function restore(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('Restore:Sucursal');
    }

    public function forceDelete(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('ForceDelete:Sucursal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Sucursal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Sucursal');
    }

    public function replicate(AuthUser $authUser, Sucursal $sucursal): bool
    {
        return $authUser->can('Replicate:Sucursal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Sucursal');
    }
}
