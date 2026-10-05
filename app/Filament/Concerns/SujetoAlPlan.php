<?php

namespace App\Filament\Concerns;

use App\Support\AlcanceDelPlan;
use Filament\Facades\Filament;

/**
 * La pantalla se abre solo si el plan del concesionario la cubre.
 *
 * Va como rasgo y no como línea suelta en cada recurso para que la regla viva
 * en un sitio: qué módulo pide cada pantalla está en AlcanceDelPlan, y aquí
 * solo se consulta. Añadir un recurso nuevo es ponerlo en ese mapa.
 *
 * Al negar el acceso Filament también lo saca del menú, del buscador global y
 * de la resolución de URLs, así que no queda el enlace muerto que invita a
 * teclear la dirección a mano.
 */
trait SujetoAlPlan
{
    public static function canAccess(): bool
    {
        return parent::canAccess()
            && AlcanceDelPlan::permitePantalla(Filament::getTenant(), static::class);
    }
}
