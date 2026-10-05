<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unidad;
use App\Support\AlcanceDelPlan;
use App\Support\Tenancy;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UnidadPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Unidad');
    }

    public function view(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('View:Unidad');
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
        return $authUser->can('Create:Unidad')
            && AlcanceDelPlan::puedeAgregar(Tenancy::empresa(), 'unidades');
    }

    public function update(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('Update:Unidad');
    }

    public function delete(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('Delete:Unidad');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Unidad');
    }

    public function restore(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('Restore:Unidad');
    }

    public function forceDelete(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('ForceDelete:Unidad');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Unidad');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Unidad');
    }

    public function replicate(AuthUser $authUser, Unidad $unidad): bool
    {
        return $authUser->can('Replicate:Unidad');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Unidad');
    }
}
