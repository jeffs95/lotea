<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Sucursal;
use Illuminate\Auth\Access\HandlesAuthorization;

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

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Sucursal');
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