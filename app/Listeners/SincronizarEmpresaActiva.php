<?php

namespace App\Listeners;

use App\Support\Tenancy;
use Filament\Events\TenantSet;

/**
 * Filament ya sabe en qué empresa está parado el usuario; esto se lo cuenta al
 * resto de la aplicación (el EmpresaScope y los roles de spatie).
 *
 * Sin esto, el panel mostraría el selector de empresa pero las consultas
 * seguirían sin filtrar. Es el cable que une las dos mitades.
 */
class SincronizarEmpresaActiva
{
    public function handle(TenantSet $event): void
    {
        // Tenancy::usar() se encarga del scope y de los roles de spatie.
        Tenancy::usar($event->getTenant());
    }
}
