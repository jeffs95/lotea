<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Venta;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VentaPolicy
{
    use HandlesAuthorization;

    /**
     * ¿Esta venta le toca?
     *
     * El listado ya viene filtrado, pero eso solo cambia lo que se ve. Quien
     * teclea el id de otra venta en la barra de direcciones llega igual, y es
     * exactamente lo que haría un vendedor con curiosidad por la comisión del
     * de al lado. Aquí es donde se le dice que no.
     */
    protected function esSuya(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('ver_ventas_ajenas')
            || (int) $venta->vendedor_id === (int) $authUser->getKey();
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Venta');
    }

    public function view(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('View:Venta') && $this->esSuya($authUser, $venta);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Venta');
    }

    public function update(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Update:Venta') && $this->esSuya($authUser, $venta);
    }

    public function delete(AuthUser $authUser, Venta $venta): bool
    {
        return $authUser->can('Delete:Venta') && $this->esSuya($authUser, $venta);
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
