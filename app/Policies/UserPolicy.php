<?php

namespace App\Policies;

use App\Support\AlcanceDelPlan;
use App\Support\Tenancy;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:User');
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
        return $authUser->can('Create:User')
            && AlcanceDelPlan::puedeAgregar(Tenancy::empresa(), 'usuarios');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:User');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}
