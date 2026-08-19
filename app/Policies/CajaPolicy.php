<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Caja;
use Illuminate\Auth\Access\HandlesAuthorization;

class CajaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Caja');
    }

    public function view(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('View:Caja');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Caja');
    }

    public function update(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('Update:Caja');
    }

    public function delete(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('Delete:Caja');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Caja');
    }

    public function restore(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('Restore:Caja');
    }

    public function forceDelete(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('ForceDelete:Caja');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Caja');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Caja');
    }

    public function replicate(AuthUser $authUser, Caja $caja): bool
    {
        return $authUser->can('Replicate:Caja');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Caja');
    }

}