<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GastoCompartido;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GastoCompartidoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GastoCompartido');
    }

    public function view(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('View:GastoCompartido');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GastoCompartido');
    }

    public function update(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('Update:GastoCompartido');
    }

    public function delete(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('Delete:GastoCompartido');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GastoCompartido');
    }

    public function restore(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('Restore:GastoCompartido');
    }

    public function forceDelete(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('ForceDelete:GastoCompartido');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GastoCompartido');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GastoCompartido');
    }

    public function replicate(AuthUser $authUser, GastoCompartido $gastoCompartido): bool
    {
        return $authUser->can('Replicate:GastoCompartido');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GastoCompartido');
    }
}
