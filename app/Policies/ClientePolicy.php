<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Cliente;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cliente');
    }

    public function view(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('View:Cliente');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cliente');
    }

    public function update(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Update:Cliente');
    }

    public function delete(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Delete:Cliente');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Cliente');
    }

    public function restore(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Restore:Cliente');
    }

    public function forceDelete(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('ForceDelete:Cliente');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Cliente');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Cliente');
    }

    public function replicate(AuthUser $authUser, Cliente $cliente): bool
    {
        return $authUser->can('Replicate:Cliente');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Cliente');
    }

}