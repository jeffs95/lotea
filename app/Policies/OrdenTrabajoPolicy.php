<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OrdenTrabajo;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrdenTrabajoPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OrdenTrabajo');
    }

    public function view(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('View:OrdenTrabajo');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OrdenTrabajo');
    }

    public function update(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('Update:OrdenTrabajo');
    }

    public function delete(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('Delete:OrdenTrabajo');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OrdenTrabajo');
    }

    public function restore(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('Restore:OrdenTrabajo');
    }

    public function forceDelete(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('ForceDelete:OrdenTrabajo');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OrdenTrabajo');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OrdenTrabajo');
    }

    public function replicate(AuthUser $authUser, OrdenTrabajo $ordenTrabajo): bool
    {
        return $authUser->can('Replicate:OrdenTrabajo');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OrdenTrabajo');
    }

}