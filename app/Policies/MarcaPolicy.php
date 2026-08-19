<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Marca;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarcaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Marca');
    }

    public function view(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('View:Marca');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Marca');
    }

    public function update(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('Update:Marca');
    }

    public function delete(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('Delete:Marca');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Marca');
    }

    public function restore(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('Restore:Marca');
    }

    public function forceDelete(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('ForceDelete:Marca');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Marca');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Marca');
    }

    public function replicate(AuthUser $authUser, Marca $marca): bool
    {
        return $authUser->can('Replicate:Marca');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Marca');
    }

}