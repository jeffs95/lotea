<?php

namespace App\Filament\Concerns;

use App\Support\AlcanceDelPlan;
use Filament\Facades\Filament;

/**
 * No se crea de más de lo que el plan permite.
 *
 * El recurso declara qué cuenta —sucursales, usuarios o unidades— y el rasgo
 * se encarga de esconder el botón de alta cuando ya no queda lugar.
 *
 * Esconder el botón sin decir nada deja a la gente buscando el que ayer
 * estaba, así que el aviso de por qué va en el subtítulo del listado, donde
 * se lee sin tener que intentarlo primero.
 */
trait RespetaElTope
{
    public static function canCreate(): bool
    {
        return parent::canCreate()
            && AlcanceDelPlan::puedeAgregar(Filament::getTenant(), static::$cuenta);
    }

    /** El aviso para el subtítulo del listado, o null si todavía hay lugar. */
    public static function avisoDelTope(): ?string
    {
        $empresa = Filament::getTenant();

        if ($empresa === null || AlcanceDelPlan::puedeAgregar($empresa, static::$cuenta)) {
            return null;
        }

        return AlcanceDelPlan::avisoDeTope($empresa, static::$cuenta)
            .' Para agregar más hay que subir de plan.';
    }
}
