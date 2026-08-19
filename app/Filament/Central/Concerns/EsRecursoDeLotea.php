<?php

namespace App\Filament\Central\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Los recursos del panel central no pasan por los permisos de Shield.
 *
 * Esos permisos existen para repartir accesos dentro de un concesionario; aquí
 * la puerta ya la controla users.es_operador, y quien entró es de Lotea. Si
 * dependiéramos de las policies, un modelo compartido entre los dos paneles
 * (como Ticket) bloquearía al operador con las reglas del cliente.
 */
trait EsRecursoDeLotea
{
    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }
}
