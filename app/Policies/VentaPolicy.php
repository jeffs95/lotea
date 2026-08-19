<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Venta;
use Illuminate\Auth\Access\HandlesAuthorization;

class VentaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Venta');
    }

    public function view(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('View:Venta');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Venta');
    }

    public function update(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Update:Venta');
    }

    public function delete(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Delete:Venta');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Venta');
    }

    public function restore(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Restore:Venta');
    }

    public function forceDelete(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('ForceDelete:Venta');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Venta');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Venta');
    }

    public function replicate(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Replicate:Venta');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Venta');
    }

}