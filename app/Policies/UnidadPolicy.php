<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unidad;
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

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Unidad');
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
