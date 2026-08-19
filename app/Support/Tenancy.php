<?php

namespace App\Support;

use App\Models\Empresa;
use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contexto de la empresa (tenant) activa.
 *
 * Todo el aislamiento entre clientes depende de esta clase: el EmpresaScope
 * pregunta aquí a quién pertenece la petición actual. En el panel se alimenta
 * sola desde el evento TenantSet de Filament; en el portal público se resuelve
 * por dominio; en consola y colas hay que fijarla a mano.
 */
class Tenancy
{
    protected static ?int $empresaId = null;

    protected static bool $sinFiltro = false;

    /**
     * Fija la empresa activa para el resto de la petición.
     *
     * Sincroniza también el contexto de spatie/permission: los roles son por
     * empresa, y si los dos contextos se separan, el cliente A termina
     * editando los roles del cliente B.
     */
    public static function usar(Empresa|int|null $empresa): void
    {
        static::$empresaId = $empresa instanceof Empresa ? $empresa->getKey() : $empresa;

        app(PermissionRegistrar::class)->setPermissionsTeamId(static::$empresaId);
    }

    public static function empresaId(): ?int
    {
        return static::$empresaId;
    }

    public static function empresa(): ?Empresa
    {
        return static::$empresaId ? Empresa::find(static::$empresaId) : null;
    }

    public static function hayEmpresa(): bool
    {
        return static::$empresaId !== null;
    }

    /** ¿El scope debe filtrar ahora mismo? */
    public static function filtrando(): bool
    {
        return static::hayEmpresa() && ! static::$sinFiltro;
    }

    public static function olvidar(): void
    {
        static::$empresaId = null;
        static::$sinFiltro = false;

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    /**
     * Escapa del filtro por empresa dentro del callback.
     *
     * Es la ÚNICA forma permitida de leer datos de varias empresas a la vez
     * (panel central, reportes globales, mantenimientos). Que sea explícito es
     * a propósito: si alguien ve esto en un diff, sabe que tiene que revisarlo.
     */
    public static function sinFiltro(Closure $callback): mixed
    {
        $anterior = static::$sinFiltro;
        static::$sinFiltro = true;

        try {
            return $callback();
        } finally {
            static::$sinFiltro = $anterior;
        }
    }

    /** Ejecuta el callback como si fuéramos otra empresa. */
    public static function comoEmpresa(Empresa|int $empresa, Closure $callback): mixed
    {
        $anterior = static::$empresaId;
        static::usar($empresa);

        try {
            return $callback();
        } finally {
            static::usar($anterior);
        }
    }
}
