<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CategoriaCosto;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CategoriaCostoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CategoriaCosto');
    }

    public function view(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('View:CategoriaCosto');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CategoriaCosto');
    }

    public function update(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('Update:CategoriaCosto');
    }

    public function delete(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('Delete:CategoriaCosto');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CategoriaCosto');
    }

    public function restore(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('Restore:CategoriaCosto');
    }

    public function forceDelete(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('ForceDelete:CategoriaCosto');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CategoriaCosto');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CategoriaCosto');
    }

    public function replicate(AuthUser $authUser, CategoriaCosto $categoriaCosto): bool
    {
        return $authUser->can('Replicate:CategoriaCosto');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CategoriaCosto');
    }
}
