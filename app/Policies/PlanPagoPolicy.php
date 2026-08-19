<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanPago;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PlanPagoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanPago');
    }

    public function view(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('View:PlanPago');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanPago');
    }

    public function update(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('Update:PlanPago');
    }

    public function delete(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('Delete:PlanPago');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PlanPago');
    }

    public function restore(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('Restore:PlanPago');
    }

    public function forceDelete(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('ForceDelete:PlanPago');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanPago');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanPago');
    }

    public function replicate(AuthUser $authUser, PlanPago $planPago): bool
    {
        return $authUser->can('Replicate:PlanPago');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlanPago');
    }
}
