<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Empleado;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmpleadoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Empleado');
    }

    public function view(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('View:Empleado');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Empleado');
    }

    public function update(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('Update:Empleado');
    }

    public function delete(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('Delete:Empleado');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Empleado');
    }

    public function restore(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('Restore:Empleado');
    }

    public function forceDelete(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('ForceDelete:Empleado');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Empleado');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Empleado');
    }

    public function replicate(AuthUser $authUser, Empleado $empleado): bool
    {
        return $authUser->can('Replicate:Empleado');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Empleado');
    }
}
