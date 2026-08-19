<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Pedir ayuda no se restringe por rol.
 *
 * Si el mecánico no puede reportar que algo no le funciona, el problema llega
 * tarde y por WhatsApp. Cualquiera abre un reporte; cada quien ve solo los
 * suyos, y los operadores de Lotea ven todos.
 *
 * OJO: `php artisan shield:generate` sobreescribe las policies. Si volvés a
 * correrlo, revisá que este archivo siga como está.
 */
class TicketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, Ticket $ticket): bool
    {
        return $usuario->es_operador || $ticket->user_id === $usuario->id;
    }

    public function create(User $usuario): bool
    {
        return true;
    }

    /** Solo Lotea responde y cierra tickets. */
    public function update(User $usuario, Ticket $ticket): bool
    {
        return (bool) $usuario->es_operador;
    }

    public function delete(User $usuario, Ticket $ticket): bool
    {
        return false;
    }

    public function deleteAny(User $usuario): bool
    {
        return false;
    }

    public function restore(User $usuario, Ticket $ticket): bool
    {
        return false;
    }

    public function forceDelete(User $usuario, Ticket $ticket): bool
    {
        return false;
    }
}
