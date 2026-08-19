<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Deja constancia de cuándo entró cada quien.
 *
 * Es lo que alimenta la alerta de clientes que dejaron de usar el sistema: sin
 * este dato, el churn se descubre cuando ya avisaron que se van.
 */
class RegistrarUltimoAcceso
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        }
    }
}
